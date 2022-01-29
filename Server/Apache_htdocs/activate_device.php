<?php
require_once 'connection_db.php';
require_once 'total_devices.php';

$total_devices = get_total_devices($db);

for($deviceID = 1; (int)$deviceID <= (int)$total_devices; (int)$deviceID++)
{   // Here we are checking which button press triggered the upload.php
    if(isset($_POST[$deviceID])){
        $update_query = $db->prepare("UPDATE devices_hotel SET recognition_status = 'DEVICE-ACTIVE' WHERE device_id = :dev_id");
        $update_query->bindValue('dev_id', $deviceID, PDO::PARAM_STR);
        if ($update_query->execute())
        {
            header("location: index.php");
        }
    }
}

?>