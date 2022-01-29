<?php
require_once 'connection_db.php';
require_once 'ip_checker.php';

if(isset($_REQUEST['login_btn']))
{
    // Data from the form submitted by the user
    $ip_add = checkingIpFormData($_REQUEST['ip_address']);
    $guest_name = $_REQUEST['name'];
    
    if (!$ip_add)
    {
        $errorMsg = "This is not a valid IP!";
    }
    else if (!$guest_name)
    {
        $errorMsg = "You must enter a guest name!";
    }
    else
    {
        try{
            // What we are doing here is checking if the ip was already present in our database
            $select_query = $db->prepare("SELECT * FROM devices_hotel WHERE device_ip = :ip");
            $select_query->bindValue('ip', $ip_add, PDO::PARAM_STR);
            $select_query->execute();
            $query_result = $select_query->fetch(PDO::FETCH_ASSOC);

            if(isset($query_result['device_id'])){
                if($guest_name == $query_result['guest_name'])
                {
                    $errorMsg = "Device is already associated to this guest!";
                }
                else // Device is already present, but we will associate it to another guest now
                {
                    $update_query = $db->prepare("UPDATE devices_hotel SET guest_name = :g_name, embeddings_path = NULL, number_of_photos=0 WHERE device_ip = :ip");
                    $update_query->bindValue('ip', $ip_add, PDO::PARAM_STR);
                    $update_query->bindValue('g_name', $guest_name, PDO::PARAM_STR);
                    if ($update_query->execute())
                    {
                        header("location: device_updated.php");
                    }
                }
            }
            else // New device will be added
            {
                $insert_query = $db->prepare("INSERT INTO devices_hotel (device_ip, guest_name, recognition_status, number_of_photos) 
                VALUES (:ip, :g_name, :recog_status, 0)");
                $insert_query->bindValue('ip', $ip_add, PDO::PARAM_STR);
                $insert_query->bindValue('recog_status', 'NO-PHOTO', PDO::PARAM_STR);
                $insert_query->bindValue('g_name', $guest_name, PDO::PARAM_STR);
                if ($insert_query->execute())
                {
                    header("location: device_added.php");
                }
            }
        }
        catch(PDOException $e){
            $pdoErrror = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-KyZXEAg3QhqLMpG8r+8fhAXLRk2vvoC2f3B09zVXn8CA5QIVfZOJ3BCsw2P0p/We" crossorigin="anonymous">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-U1DAWAznBHeqEIlVSCgzq+c9gqGAJn5c/t99JyeKa9xxaYpSvHU5awsuZVVFIhvj" crossorigin="anonymous"></script>
    <link rel="stylesheet" type="text/css" media="screen" href="style.css" />
    <title>Table with devices</title>
</head>
<body>
    <form action="index.php" method="post">
        <div class="mb-3">
            <?php
                if (isset($errorMsg))
                {
                    echo "<p class='small text-danger'>".$errorMsg."</p>";
                }
            ?>
            <label for="ip" class="form-label">Adding/Updating Device:</label>
            <input id="ip" type="text" name="ip_address" class="form-control" placeholder="192.168.1.1">
            <input id="name" type="text" name="name" class="form-control" placeholder="Michael Jordan">
            <button type="submit" name="login_btn" class="btn btn-primary">Add</button>
        </div>
	</form>
    <table class="devices-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Device IP</th>
                <th>Upload Photo</th>
                <th>Number of Photos</th>
                <th>Device Status</th>
                <th>Clean Database</th>
            </tr>
        </thead>
        <?php
            try{
                $select = $db->prepare("SELECT * FROM devices_hotel");
                $select->execute();
                
                while($row = $select->fetch(PDO::FETCH_ASSOC))
                {
                    echo "<tr>";
                    echo     "<td>".$row['device_id']."</td>";
                    echo     "<td>".$row['device_ip']."</td>";
                    // html photo_path column
                    echo     "<td>";
                    echo        "<form action='upload.php' method='POST' enctype='multipart/form-data'>";
                    echo        "<input type='file' name='file'>"; 
                    // Every button is associated with 'name' and we use this 'name' to distinguish them in upload.php
                    echo        "<button id='btn_photo' type='submit' name='".$row['device_id']."' class='btn btn-primary'>UPLOAD PHOTO</button>";
                    echo        "</form>";
                    echo     "</td>";
                    echo     "<td>".$row['number_of_photos']."</td>";
                    // device status column
                    echo     "<td>";
                    switch($row['recognition_status']){
                        case 'NO-PHOTO':
                            echo "NO PHOTO UPLOADED";
                            break;
                        case 'PHOTO-UPLOADED':
                            echo "PHOTO UPLOADED";
                            break;
                        case 'NO-FACE-DETECTED':
                            echo "UPLOAD NEW PHOTO.\nNO FACE DETECTED!";
                            break;
                        case 'EMBEDDINGS-READY':
                            echo "EMBEDDINGS READY!";
                            break;
                        case 'WAITING-ACTIVATION':
                            echo "<form action='activate_device.php' method='POST'>";
                            echo "<button id='btn_raspberry' type='submit' name='".$row['device_id']."' class='btn btn-primary'>ACTIVATE DEVICE</button>";
                            echo "</form>";
                            break;
                        case 'DEVICE-ACTIVE':
                            echo "DEVICE ACTIVE";
                            break;
                    }
                    echo     "</td>";
                    echo     "<td>";
                    if (($row['photo_path'] != 'NULL') or ($row['number_of_photos'] > 0) or ($row['embeddings_path'] != 'NULL') or ($row['recognition_status'] != 'NO-PHOTO'))
                    {
                        echo        "<form action='erase.php' method='POST' enctype='multipart/form-data'>";
                        // Every button is associated with 'name' and we use this 'name' to distinguish them in upload.php
                        echo        "<button id='btn_photo' type='submit' name='".$row['device_id']."' class='btn btn-primary'>ERASE DATA</button>";
                        echo        "</form>";
                    }
                    echo     "</td>";
                    echo "</tr>";
                }
            }
            catch(PDOException $e){
                $pdoErrror = $e->getMessage();
            }
        ?>        
    </table>
    
</body>
</html>