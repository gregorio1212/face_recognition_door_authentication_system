<?php
require_once 'connection_db.php';
require_once 'total_devices.php';

$fileSizeMax = 1000000; // 1MB
$total_devices = get_total_devices($db);

for($deviceID = 1; (int)$deviceID <= (int)$total_devices; (int)$deviceID++)
{   // Here we are checking which button press triggered the upload.php
    if(isset($_POST[$deviceID]))
    {
        $file = $_FILES['file'];

        if($file['error']){
            echo "Error uploading file. Try again.";
            header("Location: index.php");
            return;
        }
        
        /** Here we are checking if it has the type of file we want */
        $fileName = $file['name'];
        $fileNameAndExtensionArray = explode('.', $fileName);
        $fileExtension = strtolower(end($fileNameAndExtensionArray));
        $allowedExtensions = array('jpg', 'jpeg', 'png');

        if(!in_array($fileExtension, $allowedExtensions)){
            echo "Error: file extension not allowed!";
            header("Location: index.php");
            return;
        }

        /** Cheking the whole size of the file */
        $fileSize = $file['size'];
        if($fileSize > $fileSizeMax)
        {
            echo "Error: file size is bigger than the allowed size!";
            header("Location: index.php");
            return; 
        }

        /** Getting the name save so that we can name the folder */
        $select = $db->prepare("SELECT * FROM devices_hotel WHERE device_id=".$deviceID);
        $select->execute();
        $row = $select->fetch(PDO::FETCH_ASSOC);

        /** Setting and creating (if necessary) folder for saving photo */
        $folder = 'your_path'.$row['guest_name'];
        mkdir($folder);

        $new_num_of_photos = $row['number_of_photos']+1;

        /** Saving photo in proper location */
        $fileTmpLocation = $file['tmp_name'];
        $fileFinalLocation = $folder.'/photo_'.($new_num_of_photos).'.'.$fileExtension;
        move_uploaded_file($fileTmpLocation, $fileFinalLocation);

        $update = $db->prepare("UPDATE devices_hotel SET photo_path='".$folder."', 
        number_of_photos='".$new_num_of_photos."', recognition_status='PHOTO-UPLOADED'  WHERE device_id=".$deviceID);
        $update->execute();
        
        header("Location: photo_added.php");
    }
}
