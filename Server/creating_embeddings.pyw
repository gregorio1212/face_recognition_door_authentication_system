import pymysql
from imutils import paths
import face_recognition
import pickle
import cv2
import os
import time
import shutil

log_info = {
    'user' : 'your_user',
    'passwd' : 'your_password',
    'host' : 'your_server_ip',
    'db' : 'your_database_name',
}
embeddings_path = "your_embeddings_path"
Encodings = []
Name = []
DEBUG_MODE = False

if __name__ == '__main__':
    while True:
        conn = pymysql.connect(**log_info)
        cursor = conn.cursor(pymysql.cursors.DictCursor)
        
        if DEBUG_MODE:
            print("Connection to database established")

        # fetch all the guest_names
        cursor.execute("SELECT guest_name FROM devices_hotel WHERE recognition_status='PHOTO-UPLOADED'")
        guest_names = cursor.fetchall()

        if guest_names != (): # empty, no guest in PHOTO-UPLOADED state
            for guest in guest_names:
                name = guest['guest_name']
                if DEBUG_MODE:
                    print("Initializing embeddings creation for {}".format(name))
                cursor.execute("SELECT photo_path, number_of_photos FROM devices_hotel WHERE guest_name='{}'".format(name))
                guest_data = cursor.fetchone()

                # Initial settings before embeddings creation
                photo_path = guest_data['photo_path']
                number_of_photos = guest_data['number_of_photos']

                if number_of_photos > 0:
                    for filename in os.listdir(photo_path):
                        full_photo_path = os.path.join(photo_path, filename)
                        # checking if it is a file
                        if os.path.isfile(full_photo_path):
                            #print(full_photo_path)
                            
                            if DEBUG_MODE:
                                print("Face location initalizing...\n")
                            
                            # load the input image and convert it from BGR (OpenCV ordering) to dlib ordering (RGB)
                            image = cv2.imread(full_photo_path)
                            rgb = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)

                            # detect the (x, y)-coordinates of the bounding face_box corresponding to each face in the input image
                            face_box = face_recognition.face_locations(rgb, model='cnn')

                            embeddings_user_file = embeddings_path + name + ".pickle"

                            # TODO: add a way to check if there is more than one person in the photo
                            if face_box != []:
                                if DEBUG_MODE:
                                    print("Face found! Enconding iniatlizing...\n")
                                encodings = face_recognition.face_encodings(rgb, face_box)
                                os.makedirs(embeddings_path, exist_ok=True)

                                # looping and appending encondings
                                for encoding in encodings:
                                    Name.append(name)
                                    Encodings.append(encoding)

                                # Saving encodings to file
                                data = {"encodings": Encodings, "names": Name}
                                f = open(embeddings_user_file, "wb")
                                f.write(pickle.dumps(data))
                                f.close()
                                if DEBUG_MODE:
                                    print("Encondings successfully created and saved!")
                                cursor.execute("UPDATE devices_hotel SET recognition_status='EMBEDDINGS-READY', embeddings_path='" + embeddings_user_file +"' WHERE guest_name='" + name +"'")
                                conn.commit()
                                if DEBUG_MODE:
                                    print("Database successfuly updated!")
                            else:
                                cursor.execute("UPDATE devices_hotel SET recognition_status='NO-PHOTO', embeddings_path='NULL', number_of_photos=0, photo_path='NULL' WHERE guest_name='" + name +"'")
                                conn.commit()
                                shutil.rmtree(photo_path)
                                if os.path.isfile(embeddings_user_file):
                                    os.remove(embeddings_user_file)
                                if DEBUG_MODE:
                                   print("Folder with photos and embeddings delete")
                                   print("ERROR: Photo had no face!")
        else:
            if DEBUG_MODE:
                print("No new user needs embeddings")            
        conn.close()
        time.sleep(10)
