<?php
// Remember you must be connected to the database to use this function => require_once 'connection_db.php';

function get_total_devices($db)
{
    /** Procedure to get the number of devices */
    $select = $db->prepare("SELECT COUNT(*) FROM devices_hotel");
    $select->execute();
    $totalRows = $select->fetch(PDO::FETCH_ASSOC);
    $totalRows = (int)$totalRows['COUNT(*)'];
    return $totalRows;
}
