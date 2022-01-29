<?php
function checkingIpFormData($ip){
    $numbers = array('0','1', '2', '3','4', '5', '6','7', '8', '9');
    $size_ip = strlen($ip);
    $size_num = sizeof($numbers);

    //checking size, first condition
    if(($size_ip < 7) or ($size_ip > 15) or ($ip[0] == '.'))
    {
        return false;
    }
    
    # Variables used to check dot position and number of digist present between each digit
    $num_count = 0;
    $was_dot_previously = false;
    $dot_count = 0;
    # Variables used to check the 255 limit
    $octet = 0;
    $was_number_previously = false;

    for($i=0; $i < $size_ip ; $i++) {
        for($j=0; $j < $size_num ; $j++) {
            if ($ip[$i] == $numbers[$j])
            {
                $was_dot_previously = false;
                
                // Procedure for checking max value = 255
                if(($octet != 0) or ($num_count == 3)){
                    $octet = 10 * $octet;
                }
                $octet += $numbers[$j];

                if ($octet > 255)
                {
                    return false;
                }
                
                // we can end iteration now since value was found
                $j = $size_num;
                $num_count++;
            }

            // if any of those are met, we dot not have a valid IP address
            if (($ip[$i] == '.' and $was_dot_previously) or $dot_count > 3 or $num_count > 3)
            {
                return false;
            }

            if ($ip[$i] == '.')
            {
                $octet = 0;
                $j = $size_num;
                $dot_count++;
                $was_dot_previously = true;
                $num_count = 0;
            }
        }
    }

    if($dot_count == 3)
    {
        return $ip;
    }
    return false;
}
?>