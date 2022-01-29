#! /usr/bin/ python3.7
import time
# Sleep time necessary because during raspberry pi bootup importing the following libraries caused crashes
time.sleep(10)

from fileinput import filename
import RPi.GPIO as GPIO
import picamera
import face_recognition
import argparse
import pickle
import cv2
import pymysql
from ftplib import FTP
import netifaces as nf
from datetime import datetime

DEBUG_MODE = False

# GPIO Ports assignment - 'macros'
BUTTON = 24
LED_RED = 23
LED_YELLOW = 25
LED_GREEN = 18

# Database related variables
device_state = "NO-PHOTO"
log_info = {
    'user' : 'your_user',
    'passwd' : 'your_password',
    'host' : 'your_server_ip',
    'db' : 'your_database_name',
}

# Raspberry Pi IP (wlan0 might be different in your case)
rpi_ip = nf.ifaddresses('wlan0')[nf.AF_INET][0]['addr']

# Sleep time while not in DEVICE-ACTIVE mode
sleep_time_sec = 15

# Setting LEDs to LOW
def setting_gpios_off():
	GPIO.output(LED_RED, GPIO.LOW)
	GPIO.output(LED_YELLOW, GPIO.LOW)
	GPIO.output(LED_GREEN, GPIO.LOW)
# Setting up GPIO ports configuration
def setting_up_gpios():
	# Here we set the pin-numbering scheme we will use
	GPIO.setmode(GPIO.BCM)
	GPIO.setup(LED_RED, GPIO.OUT)
	GPIO.setup(LED_YELLOW, GPIO.OUT)
	GPIO.setup(LED_GREEN, GPIO.OUT)
	GPIO.setup(BUTTON, GPIO.IN, pull_up_down=GPIO.PUD_UP)
	setting_gpios_off()

def button_not_pressed_leds():
	GPIO.output(LED_RED, GPIO.LOW)
	GPIO.output(LED_GREEN, GPIO.LOW)
	GPIO.output(LED_YELLOW, GPIO.HIGH)

def button_pressed_leds_pattern_before_capture():
	GPIO.output(LED_RED, GPIO.HIGH)
	GPIO.output(LED_YELLOW, GPIO.HIGH)
	time.sleep(0.5)
	GPIO.output(LED_RED, GPIO.LOW)
	GPIO.output(LED_YELLOW, GPIO.LOW)
	time.sleep(0.5)

def get_embeddings_from_ftp_server(guest_name):
	ftp = FTP('your_ftp_server_ip')
	ftp.login('your_ftp_server_user', 'your_ftp_server_password')
	# change directory
	ftp.cwd('your_directory_for_embeddings')
	embeddings_file = "{}.pickle".format(guest_name)
	with open(embeddings_file, "wb") as file:
		ftp.retrbinary(f"RETR {embeddings_file}", file.write) # later decide if here it's the proper place to safe it
	ftp.quit()

def formatting_file_name(guest_name):
	datetimenow = datetime.now()
	date_time = datetimenow.strftime("%Y-%m-%d_%H-%M-%S")
	return guest_name + "_" + date_time + ".jpg"

if __name__ == '__main__':
	setting_up_gpios()
	cam = picamera.PiCamera()

	try:
		while True:
			conn = pymysql.connect(**log_info)
			cursor = conn.cursor(pymysql.cursors.DictCursor)
			cursor.execute("SELECT * FROM devices_hotel WHERE device_ip='{}'".format(rpi_ip))
			query_result = cursor.fetchone()

			device_state = query_result['recognition_status']
			guest_name = query_result['guest_name']
			# initializing face recognition name
			face_recog_name = "Access_Denied"

			if device_state == "EMBEDDINGS-READY":
				get_embeddings_from_ftp_server(guest_name)
				cursor.execute("UPDATE devices_hotel SET recognition_status='WAITING-ACTIVATION' WHERE device_ip='{}'".format(rpi_ip))
				conn.commit()
				if DEBUG_MODE:
					print("Embeddings from ftp server Downloaded")

			elif device_state == "WAITING-ACTIVATION":
				GPIO.output(LED_RED, GPIO.HIGH)
				if DEBUG_MODE:
					print("Device ready for face recognition")

			elif device_state == "DEVICE-ACTIVE":
				if GPIO.input(BUTTON): # Button not pressed/HIGH
					button_not_pressed_leds()
					sleep_time_sec = 2
				else: # Button pressed/LOW
					photo_timer = 4
					while photo_timer > 0:
						button_pressed_leds_pattern_before_capture()
						photo_timer -= 1

					file_name = formatting_file_name(guest_name)
					cam.capture(file_name)

					if DEBUG_MODE:
						print("[INFO] loading encodings...")
					
					data = pickle.loads(open(guest_name + ".pickle", "rb").read())

					image = cv2.imread(file_name)
					rgb = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)

					if DEBUG_MODE:
						print("[INFO] recognizing faces...")

					face_box = face_recognition.face_locations(rgb, model='hog')
					encodings = face_recognition.face_encodings(rgb, face_box)
					
					names = []
					# loop over the facial embeddings from the taken photo
					for encoding in encodings:
						matches = face_recognition.compare_faces(data["encodings"], encoding)
						# Looking for matches between encondings from ftp server and the one created now
						if True in matches:
							matchedIdxs = [i for (i, b) in enumerate(matches) if b]
							counts = {}
							for i in matchedIdxs:
								face_recog_name = data["names"][i]
								counts[face_recog_name] = counts.get(face_recog_name, 0) + 1
							face_recog_name = max(counts, key=counts.get)
	
						# update the list of names
						names.append(face_recog_name)
    
					# loop over the recognized faces (if there is more than one)
					for ((top, right, bottom, left), face_recog_name) in zip(face_box, names):
						# draw the predicted face face_recog_name on the image
						cv2.rectangle(image, (left, top), (right, bottom), (0, 255, 0), 2)
						y = top - 15 if top - 15 > 15 else top + 15
						# Adding Access_Denied prefix to access photo
						if face_recog_name == "Access_Denied":
							file_name = face_recog_name + "_" + file_name
						cv2.putText(image, face_recog_name, (left, y), cv2.FONT_HERSHEY_COMPLEX, 0.75, (0, 255, 0), 2)
						cv2.imwrite(file_name, image)

					# connect to host, default port
					ftp = FTP('your_ftp_server_ip')
					ftp.login('your_ftp_server_user', 'your_ftp_server_password')
					# change directory
					ftp.cwd('your_folder_for_saving_access_photos')

					with open(file_name, "rb") as file:
						ftp.storbinary(f"STOR {file_name}", file)
					ftp.quit()

					sleep_time_sec = 20
					
					if face_recog_name == guest_name:
						GPIO.output(LED_GREEN, GPIO.HIGH)
					else:
						GPIO.output(LED_RED, GPIO.HIGH)
					
					if DEBUG_MODE:
						print("Wait 20 seconds for next try")
				
				if DEBUG_MODE:
					print("Procedure involving LEDs and taking photo and while loop until device_state changes")
			
			elif device_state == "NO-PHOTO":
				setting_gpios_off()
				sleep_time_sec = 15
				if DEBUG_MODE:
					print("User has no photo")

			conn.close()
			time.sleep(sleep_time_sec)
			
	except KeyboardInterrupt:
		GPIO.cleanup()
