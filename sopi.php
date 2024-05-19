<?php
date_default_timezone_set('Asia/Jakarta');

$cookiesFilePath = 'cokAkun.txt';
$cookies = readCookiesFromFile($cookiesFilePath);
$chatId = "ID TELEGRAM LU";
$botToken = "BOT TOKEN TELEGRAM LU";

cekAkun();
cekStock();

while (true) {
    echo "\n\n[1.] CLAIM BY KEYWORD BARANG\n";
    echo "[2.] CLAIM BY ID BARANG\n";
    $choice = readline('Masukkan pilihan Anda (1/2): ');

    if ($choice == 1) {
        $keyword = readline('MASUKKAN KEYWORD / NAMA BARANG => : ');
        if (empty($keyword)) {
            echo "KEYWORD / NAMA BARANG TIDAK BOLEH KOSONG!! SILAHKAN DIISI!!\n";
            continue;
        }
        cekNamaBarang($keyword);
    } elseif ($choice == 2) {
        $idBarang = readline('MASUKKAN ID BARANG => : ');
        if (empty($idBarang)) {
            echo "ID BARANG KOSONG!! SILAHKAN DIISI!!\n";
            continue;
        }
        cekId($idBarang);
    } else {
        echo "Pilihan tidak valid. Silakan pilih 1 atau 2.\n";
    }
}


function cekAkun()
{
    global $cookies, $userid, $username, $email;

    $sessionUrl = "https://shopee.co.id/api/v4/account/basic/get_account_info";
    $response = makeGetRequest($sessionUrl, ['Cookie' => $cookies]);
    $response = json_decode($response, true);

    if ($response['error'] === 0) {
        $userid = $response['data']['userid'];
        $username = $response['data']['username'];
        $email = $response['data']['email'];
        echo "DATA AKUN:\nUSERNAME: $username\nEMAIL: $email\n\n";
    }
}

function cekStock()
{
    global $cookies, $userid;

    $sessionUrl = "https://idgame.shopee.co.id/api/buyer-mission/v2/quests/f32fb098ab94baef/store/items";
    $response = makeGetRequest($sessionUrl, ['x-user-id' => $userid, 'Cookie' => $cookies]);
    $response = json_decode($response, true);

    if ($response['code'] === 0) {
        $processedNames = [];
        foreach ($response['data']['item_list'] as $item) {
            $nameUpper = strtoupper($item['name']);
            if (!in_array($nameUpper, $processedNames)) {
                $processedNames[] = $nameUpper;
                $idBarang = $item['id'];
                $status = $item['redeem_status'] == 1 ? 'HABIS!!' : 'READY!!';
                $cost = isset($item['cost']) ? $item['cost'] : (isset($item['points_to_redeem']) ? $item['points_to_redeem'] : 'N/A');
                echo "[+] ID: {$idBarang} NAME: {$nameUpper} | HARGA: {$cost} POINT => {$status}\n";
            }
        }
    } else {
        echo "Error: Unexpected response code " . $response['code'] . "\n";
    }
}

function cekId($idBarang)
{
    global $cookies, $userid, $username;

    while (true) {
        $sessionUrl = "https://idgame.shopee.co.id/api/buyer-mission/v2/quests/f32fb098ab94baef/store/items";
        $response = makeGetRequest($sessionUrl, ['x-user-id' => $userid, 'Cookie' => $cookies]);
        $response = json_decode($response, true);

        if (isset($response['data']['item_list'])) {
            foreach ($response['data']['item_list'] as $item) {
                if ($item['id'] == $idBarang) {
                    $itemName = strtoupper($item['name']);
                    if ($item['redeem_status'] == 1) {
                        echo "[" . date('H:i:s') . "] BARANG ITEM ID: ({$idBarang}) HABIS / BELUM TERSEDIA!!\n";
                    } else if ($item['redeem_status'] === 0) {
                        $redeemUrl = "https://idgame.shopee.co.id/api/buyer-mission/v2/quests/f32fb098ab94baef/store/redeem/$idBarang";
                        $redeemResponse = makePostRequest($redeemUrl, [], ['x-user-id' => $userid, 'Cookie' => $cookies]);
                        $redeemResponse = json_decode($redeemResponse, true);

                        if ($redeemResponse['code'] === 0 && $redeemResponse['data']['code'] == 0) {
                            $point = $redeemResponse['data']['remain_score'];
                            echo "[+] [" . date('H:i:s') . "] " . strtoupper($redeemResponse['msg']) . " CLAIM BARANG {$itemName} | ID: ({$idBarang})!! POINT TERSISA: {$point}\n";
                            sendTelegramMessage($username, $itemName, $idBarang, $point);
                        } else {
                            echo "[!] [" . date('H:i:s') . "] " . strtoupper($redeemResponse['msg']) . " CLAIM BARANG ID: ({$idBarang}) GAGAL!!!!\n";
                        }
                    }
                }
            }
        } else {
            echo "[" . date('H:i:s') . "] BARANG YANG DICLAIM HABIS ATAU BARANG ITEM ID YANG DIMASUKKAN TIDAK DITEMUKAN!!\n";
        }

        // sleep($delay);
    }
}

function cekNamaBarang($keyword)
{
    global $cookies, $userid, $username, $botToken, $chatId;

    while (true) {
        $sessionUrl = "https://idgame.shopee.co.id/api/buyer-mission/v2/quests/f32fb098ab94baef/store/items";
        $response = makeGetRequest($sessionUrl, ['x-user-id' => $userid, 'Cookie' => $cookies, 'Content-type' => 'application/json']);
        $response = json_decode($response, true);

        if (isset($response['data']['item_list'])) {
            foreach ($response['data']['item_list'] as $item) {
                if (stripos($item['name'], $keyword) !== false) {

                    $itemName = strtoupper($item['name']);
                    if ($item['redeem_status'] == 1) {
                        echo "[" . date('H:i:s') . "] BARANG ITEM NAME: " . strtoupper($item['name']) . " ({$item['id']}) HABIS / BELUM TERSEDIA!!\n";
                    } else if ($item['redeem_status'] === 0) {
                        $idBarang = $item['id'];
                        $redeemUrl = "https://idgame.shopee.co.id/api/buyer-mission/v2/quests/f32fb098ab94baef/store/redeem/$idBarang";
                        $redeemResponse = makePostRequest($redeemUrl, [], ['x-user-id' => $userid, 'Cookie' => $cookies]);
                        $redeemResponse = json_decode($redeemResponse, true);

                        if ($redeemResponse['code'] === 0 && $redeemResponse['data']['code'] == 0) {
                            $point = $redeemResponse['data']['remain_score'];
                            echo "[+] [" . date('H:i:s') . "] " . strtoupper($redeemResponse['msg']) . " CLAIM BARANG {$itemName} | ID: ({$idBarang})!! POINT TERSISA: {$point}\n";
                            sendTelegramMessage($username, $itemName, $idBarang, $point, $botToken, $chatId);
                        } else {
                            echo "[!] [" . date('H:i:s') . "] " . strtoupper($redeemResponse['msg']) . " CLAIM BARANG NAME: ({$itemName}[{$idBarang}]) GAGAL!!!!\n";
                        }
                    }
                }
            }
        } else {
            echo "[" . date('H:i:s') . "] BARANG YANG DICLAIM HABIS ATAU BARANG DENGAN KATA KUNCI YANG DIMASUKKAN TIDAK DITEMUKAN!!\n";
        }

        // sleep($delay);
    }
}

function sendTelegramMessage($username, $itemName, $itemId, $point, $botToken, $chatId)
{
    $date = date("d-M");
    $message = "BERHASIL CLAIM BARANG!! ( ID: " . strtoupper($username) . " )\nTIME: $date " . date('H:i:s') . "\nBARANG:\n{$itemName} -  ( {$itemId} )\nPOINT TERSISA: {$point}";

    $telegramApiUrl = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message
    ];

    makePostRequest($telegramApiUrl, $data);
}

function readCookiesFromFile($filePath)
{
    try {
        $cookies = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($cookies === false) {
            error_log('ERROR!! GAGAL MEMBACA DATA COOKIE DARI FILE: ' . $filePath);
            return null;
        }
        return implode('; ', $cookies);
    } catch (Exception $error) {
        error_log('ERROR!! GAGAL MEMBACA DATA COOKIE DARI FILE!!');
        return null;
    }
}

function getHeaders($additionalHeaders = [])
{
    $defaultHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*',
        'accept-language: en-US,en;q=0.9,id;q=0.8',
        'affiliate-program-type: 1',
        'dnt: 1',
        'priority: u=1, i',
        'sec-ch-ua: "Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
    ];

    foreach ($additionalHeaders as $key => $value) {
        $defaultHeaders[] = "$key: $value";
    }

    return $defaultHeaders;
}


function makeGetRequest($url, $headers = [])
{
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => getHeaders($headers),
        ],
    ];
    $context = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

function makePostRequest($url, $data = [], $headers = [])
{
    // Ensure Content-type is set correctly
    if (!isset($headers['Content-type'])) {
        $headers['Content-type'] = 'application/json';
    }
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => getHeaders($headers),
            'content' => json_encode($data),
        ],
    ];
    $context = stream_context_create($options);
    return file_get_contents($url, false, $context);
}
