<?php

const threshold = 5.0;  // 5 KWh
const httpTooManyRequests = 429; // if there were too many http calls in a short time against the API
const sleepSeconds = 17; // waiting if a httpTooManyRequests occures
const numberOfRetries = 10;
const apiKeyFile = "api-key.txt";
const siteId = "site-id.txt";
const urlApiFormat = "https://monitoringapi.solaredge.com/site/%s/currentPowerFlow?api_key=%s";
const urlShellyOn = "http://172.31.31.xxx/relay/0?turn=on";
const urlShellyOff = "http://172.31.31.xxx/relay/0?turn=off";

// we dont't want to see Errors - only in Log
ini_set('display_errors', 0);

main();

function main() {
    $retryCountdown = numberOfRetries;
    $success = false;
    $exitWithError = false;
    $currentPower = -1.0;

    do {
        $content = getHttpFileContent(determineUrl());

        if ($content === FALSE) { // handle error here...
            $httpCode = determineHTTPStatusCode();
            if ($httpCode == httpTooManyRequests) {
                $retryCountdown --;
                echo(getCurrentDateTimeFormated() . "oh no " . httpTooManyRequests . " try again in " . sleepSeconds . " seconds. Still " . $retryCountdown . " tries left\n");
                sleep(sleepSeconds);
                // lets try again!
            } else {
                $exitWithError = true;
                echo(getCurrentDateTimeFormated() . "other Error " . error_get_last()['message'] . "\n");
            }
        } else {
            $currentPower = extractPowerValue($content);
            $success = true;
        }
    } while (!$success && !$exitWithError && $retryCountdown > 0);

    if ($success) {
        if ($currentPower > threshold) {
            switchingAdvertismentOff();
            echo(getCurrentDateTimeFormated() . "switch OFF - " . $currentPower . "KWh \n");
        } else {
            switchingAdvertismentOn();
            echo(getCurrentDateTimeFormated() . "switch ON - " . $currentPower . "KWh\n");
        }
    } else {
        switchingAdvertismentOn();
        echo(getCurrentDateTimeFormated() . "switch ON => fail-safe defense programming!!\n");
    }
}

function switchingAdvertismentOn() {
    switchingAdvertisment(true);
}

function switchingAdvertismentOff() {
    switchingAdvertisment(false);
}

function switchingAdvertisment($on) {
    if ($on) {
        $url = urlShellyOn;
    } else {
        $url = urlShellyOff;
    }
    $content = getHttpFileContent($url);
    if ($content === FALSE) {
        echo(getCurrentDateTimeFormated() . "Error occured on switching Shelly! " . error_get_last()['message'] . "\n");
    } else {
        echo(getCurrentDateTimeFormated() . "Shelly switched successfully!\n");
    }
}

function getHttpFileContent($url) {
    $content = file_get_contents($url);
    return $content;
}

function extractPowerValue($content) {
    $jsonObject = json_decode($content);
    $status = "";
    $currentPower = -1.0;

    foreach($jsonObject as $key1 => $value1) {
        if ($key1 == "siteCurrentPowerFlow") {
            foreach($value1 as $key2 => $value2) {
                if ($key2 == "PV") {
                    foreach($value2 as $key3 => $value3) {
                        if ($key3 == "status") {
                            $status = $value3;
                        } else if ($key3 == "currentPower") {
                            $currentPower = $value3;
                        }
                    }
                }
            }
        }
    }

    if ($status == "Active" && $currentPower >= 0) {
        return $currentPower;
    } else {
        return -1.0;
    }
}

function determineHTTPStatusCode() {
    $message = error_get_last()['message'];
    $pos1 = strrpos($message, "HTTP");
    if ($pos1 !== false) {
        $sub1 = substr($message, $pos1);
        $pos2 = strpos($sub1, " ");
        if ($pos2 !== false) {
            return substr($sub1, $pos2 + 1, 3);
        }
    }
    return "not Available";
}

function determineUrl() {
    $apiKey = file_get_contents(apiKeyFile);
    $siteId = file_get_contents(siteId);
    return sprintf(urlApiFormat, $siteId, $apiKey);
}

function getCurrentDateTimeFormated() {
    return date('Y-m-d H:i:s') . " ";
}

?>
