<?php

function solution($S) {
    // write your code in PHP7.0
    $cleanS = preg_replace("/[^0-9]/", "", $S);
    if (strlen($cleanS) < 4) {
        return $cleanS;
    }
    $newString = "";
    $flag = true;
    while ($flag == true) {
        $cleanSLength = strlen($cleanS);
        if ($cleanSLength == 3) {
            $newString .= $cleanS;
            $cleanS = "";
            $flag = false;
        }
        if ($cleanSLength == 4) {
            $newString .= substr($cleanS, 0, 2) . "-" . substr($cleanS, 2);
            $cleanS = "";
            $flag = false;
        }
        if ($cleanSLength == 5) {
            $newString .= substr($cleanS, 0, 3) . "-" . substr($cleanS, 3);
            $cleanS = "";
            $flag = false;
        }
        if ($cleanSLength > 5) {
            $newString .= substr($cleanS, 0, 3);
            $cleanS = substr($cleanS, 3);
        }
        if (strlen($cleanS) > 0) {
            $newString .= "-";
            continue;
        }
        $flag = false;
    }
    return $newString;
}

print_r(solution("555372654"));