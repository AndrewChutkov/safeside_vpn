<?php
require_once '../../../vendor/autoload.php';
require_once 'connect.php';
require_once 'buttons.php';

use YooKassa\Client;

$data = json_decode(file_get_contents('php://input'), true);

$contact = $data['message']['contact'];
$text = $data['message']['text'];
$callback_data = $data['callback_query']['data'];
$callback_text = $data['callback_query']['message']['text'];
$id_user = $data['message']['chat']['id'];
$id_user_callback = $data['callback_query']['from']['id'];
$username_callback = $data['callback_query']['from']['username'];

file_put_contents('file.txt', print_r($data, true), FILE_APPEND);

define('TOKEN', 'TOKEN');

// Функция вызова методов API
function sendTelegram($method, $response)
{
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/' . $method);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $res = curl_exec($ch);
    curl_close($ch);

    return $res;
}


//Ответ бота
switch ($text) {
    //******************** СТАРТОВЫЙ ЭКРАН **************************
    case '/start':
        sendTelegram(
            'sendMessage',
            array(
                'chat_id' => $data['message']['chat']['id'],
                'text' => file_get_contents(__DIR__ . '/texts/main.txt'),
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'resize_keyboard' => true,
                    'keyboard' => [
                        [
                            ['text' => $howSetup],
                            ['text' => $buy],
                        ],
                        [
                            ['text' => $extend],
                            ['text' => $status],
                        ],
                    ]
                ])
            )
        );
        break;

    //Кнопка "Как настроить"
    case $howSetup:
        sendTelegram(
            'sendMessage',
            array(
                'chat_id' => $data['message']['chat']['id'],
                'text' => file_get_contents(__DIR__ . '/texts/instruction.txt'),
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => 'iOS', 'url' => $ios_url],
                            ['text' => 'Android', 'url' => $android_url],
                        ],
                        [
                            ['text' => 'MacOS', 'url' => $macos_url],
                            ['text' => 'Windows', 'url' => $windows_url],
                        ],
                    ]
                ])
            )
        );
        break;

    //Кнопка "Купить"
    case $buy:
        sendTelegram(
            'sendMessage',
            array(
                'chat_id' => $data['message']['chat']['id'],
                'text' => file_get_contents(__DIR__ . '/texts/buy.txt'),
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => $buy_30_days, 'callback_data' => $pay],
                        ],
                    ]
                ])
            )
        );
        break;

    //Кнопка "Продлить"
    case $extend:
        $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
        $sql = "SELECT * FROM `files` WHERE id_user = '$id_user' ORDER BY name_file ASC";
        $query = mysqli_query($conn, $sql);
        $check_query = mysqli_fetch_row($query);
        mysqli_close($conn);

        if ($check_query == 0) {
            sendTelegram(
                'sendMessage',
                array(
                    'chat_id' => $data['message']['chat']['id'],
                    'text' =>  file_get_contents(__DIR__ . '/texts/subscription_not_active.txt'),
                )
            );
        } else {
            foreach ($query as $row_files) {
                $name_file = $row_files['name_file'];
                $date_subscription = $row_files['date_end'];
                sendTelegram(
                    'sendMessage',
                    array(
                        'chat_id' => $data['message']['chat']['id'],
                        'parse_mode' => 'HTML',
                        'text' => '<b>' . $name_file . '</b>',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => $extend_30_days, 'callback_data' => $prolong],
                                ],
                            ]
                        ])
                    )
                );
            }
        }
        break;

    //Кнопка "Узнать статус"
    case $status:
        $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
        $sql = "SELECT * FROM `files`  WHERE id_user = '$id_user' ORDER BY name_file ASC";
        $query = mysqli_query($conn, $sql);
        $check_query = mysqli_fetch_row($query);
        mysqli_close($conn);

        if ($check_query == 0) {
            sendTelegram(
                'sendMessage',
                array(
                    'chat_id' => $data['message']['chat']['id'],
                    'text' => file_get_contents(__DIR__ . '/texts/subscription_not_active.txt'),
                )
            );
        } else {
            foreach ($query as $row_files) {
                $name_file = $row_files['name_file'];
                $date_subscription = $row_files['date_end'];
                $date_subscription = date('d.m.Y', strtotime($date_subscription));

                sendTelegram(
                    'sendMessage',
                    array(
                        'chat_id' => $data['message']['chat']['id'],
                        'parse_mode' => 'HTML',
                        'text' => 'Ваш <b>'  . $name_file . '</b> активен до ' . $date_subscription . PHP_EOL . 'Чтобы продлить доступ на 30 дней, нажмите кнопку «Продлить» ⤵️',
                    )
                );
            }
        }
        break;

    //Заглушка
    default:
        sendTelegram(
            'sendMessage',
            array(
                'chat_id' => $data['message']['chat']['id'],
                'parse_mode' => 'HTML',
                'text' => file_get_contents(__DIR__ . '/texts/plug.txt'),
            )
        );
        break;
}

//Inline-кнопки
switch ($callback_data) {
    /*Кнопка "Купить 30 дней".
    Создается платеж, пользователю выводится ссылка на этот платеж и в бд создается запись формата:
    id платежа | статус оплаты | id пользователя | username
    */
    case $pay:
        $client = new Client();
        $client->setAuth($login_yoomoney, $password_yoomoney);

        $payment = $client->createPayment(
            array(
                'amount' => array(
                    'value' => '149.00',
                    'currency' => 'RUB',
                ),
                'confirmation' => array(
                    'type' => 'redirect',
                    'return_url' => 'https://www.example.com/return_url.php',
                ),
                'capture' => true,
                'description' => 'Оплата подписки на 30 дней',
            ),
            uniqid('', true)
        );

        $confirmationUrl = $payment->getConfirmation()->getConfirmationUrl();
        $id_payment = $payment->getId();


        sendTelegram(
            'sendMessage',
            array(
                'chat_id' => $data['callback_query']['from']['id'],
                'text' => file_get_contents(__DIR__ . '/texts/pay.txt') . PHP_EOL . $confirmationUrl,
            )
        );

        $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
        $sql = "INSERT INTO `payments` (id_payment, id_user, username) VALUES ('$id_payment', '$id_user_callback', '$username_callback')";
        $query = mysqli_query($conn, $sql);
        mysqli_close($conn);

        break;

    /* Как только оплата пройдет, идет запрос в бд — первый найденный файл без статуса 'paid'
    отправляется пользователю. После в бд, в таблицу 'files' идет запись в формате:
    name_file | id_file | status | id_user | userame | date_end
    Первые 2 поля уже заполнены, к дате добавляется 30 дней
      */
    case $paid:
        $date = date('Y-m-d H:i:s');
        $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
        $sql = "SELECT * FROM `files` WHERE status != 'paid' ORDER BY name_file ASC";
        $query = mysqli_query($conn, $sql);
        foreach ($query as $row_files) {
            $id_file = $row_files['id_file'];
            if (!empty($id_file)) {
                break;
            }
        }

        $date_end = date('Y-m-d H:i:s', strtotime('+2592000 sec'));
        $sql = "UPDATE `files` SET status = 'paid', id_user = '$id_user_callback', username = '$username_callback', date_end = '$date_end'   WHERE id_file = '$id_file'";
        $query = mysqli_query($conn, $sql);
        mysqli_close($conn);

        sendTelegram(
            'sendDocument',
            array(
                'chat_id' => $data['callback_query']['from']['id'],
                'document' => $id_file,
                'parse_mode' => 'HTML',
                'caption' => file_get_contents(__DIR__ . '/texts/paid.txt'),
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => 'iOS', 'url' => $ios_url],
                            ['text' => 'Android', 'url' => $android_url],
                        ],
                        [
                            ['text' => 'MacOS', 'url' => $macos_url],
                            ['text' => 'Windows', 'url' => $windows_url],
                        ],
                    ]
                ])
            )
        );
        break;

    /*Кнопка "Продлить на 30 дней"
      После нажатия кнопки формируется платеж и пользователю приходит ссылка на этот платеж.
      В бд 'payments' создается запись платежа, формат такой же, как в кнопке "Купить 30 дней", см. выше
    */
    case $prolong:
        $client = new Client();
        $client->setAuth($login_yoomoney, $password_yoomoney);
        $payment = $client->createPayment(
            array(
                'amount' => array(
                    'value' => '149.00',
                    'currency' => 'RUB',
                ),
                'confirmation' => array(
                    'type' => 'redirect',
                    'return_url' => 'https://www.example.com/return_url.php',
                ),
                'capture' => true,
                'description' => $callback_text,
            ),
            uniqid('', true)
        );

        $confirmationUrl = $payment->getConfirmation()->getConfirmationUrl();
        $id_payment = $payment->getId();


        sendTelegram(
            'sendMessage',
            array(
                'chat_id' => $data['callback_query']['from']['id'],
                'text' => file_get_contents(__DIR__ . '/texts/pay.txt') . PHP_EOL . $confirmationUrl,
            )
        );

        $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
        $sql = "INSERT INTO `payments` (id_payment, id_user, username) VALUES ('$id_payment', '$id_user_callback', '$username_callback')";
        $query = mysqli_query($conn, $sql);
        mysqli_close($conn);

        break;

    /* Как только платеж на продление пройдет, идет запрос в бд для увеличения даты окончания на 30 дней
      */
    case $prolongation:
    {
        $name_file = $data['callback_query']['message']['text'];
        $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);


        $sql = "UPDATE `files` SET `date_end` = DATE_ADD(`date_end` , INTERVAL 30 DAY)  WHERE name_file = '$name_file'";
        $query = mysqli_query($conn, $sql);
        mysqli_close($conn);

        sendTelegram(
            'sendMessage',
            array(
                'chat_id' => $data['callback_query']['from']['id'],
                'text' => file_get_contents(__DIR__ . '/texts/extend.txt') . ' ' . $name_file  . ' продлён на 30 дней.',
            )
        );
    }

    case $reminder:
        sendTelegram(
            'sendMessage',
            array(
                'chat_id' => $data['callback_query']['from']['id'],
                'text' => file_get_contents(__DIR__ . '/texts/reminder.txt'),
            )
        );
}
