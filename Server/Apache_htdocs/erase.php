<?php
require_once 'connection_db.php';
require_once 'total_devices.php';

$total_devices = get_total_devices($db);

for($deviceID = 1; (int)$deviceID <= (int)$total_devices; (int)$deviceID++)
{   // Here we are checking which button press triggered the erase.php
    if(isset($_POST[$deviceID]))
    {   // Getting guest name to delete its photos and embeddings
        $select_query = $db->prepare("SELECT guest_name FROM devices_hotel WHERE device_id =".$deviceID);
        $select_query->execute();
        $query_result = $select_query->fetch(PDO::FETCH_ASSOC);

        $photos_path = "enter_your_path_here".$query_result['guest_name'];
        $embeddings_path = "enter_your_path_here".$query_result['guest_name'].".pickle";
        $access_path = "enter_your_path_here";
        // Deleting embeddings
        unlink($embeddings_path);
        // Deleting all photos from a specific user
        array_map('unlink', glob("{$photos_path}/*.*"));
        if (is_dir($photos_path)) {
            rmdir($photos_path);
        }
        // Deleting door access photos
        array_map('unlink', glob("{$access_path}*.*"));
        
        // Cleaning user data
        $clean_row = $db->prepare("UPDATE devices_hotel SET photo_path = NULL, number_of_photos=0, embeddings_path = NULL, recognition_status = 'NO-PHOTO' WHERE device_id =".$deviceID);
        if ($clean_row->execute())
        {
            header("location: index.php");
        }
    }
}
