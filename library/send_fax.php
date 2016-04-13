<?php
require_once 'helper.php';
require_once 'fpdi.php';
require_once 'Zend/Mail.php';

function send_mail($content,$receiver_fax) {
    //read ini
    $config = new Zend_Config_Ini('../application/configs/application.ini', 'staging');
    $email_addr = $config->fax->email_addr;
    $email_pwd = $config->fax->email_pwd;

    $transport = array('server' => 'smtp.gmail.com',
        'config' => array('auth' => 'login', 'username' => $email_addr,
            'password' => $email_pwd,
            'ssl' => 'ssl'));

    $content = array('body' => ' ',
        'subject' => ' ',
        'attachment' => array('file_path' => $content['file_path'], 'file_type' => 'application/octet-stream')
    );
    $from = array('email_addr' => $email_addr, 'name' => 'ebsllc');
    $recv_email_addr='1'.$receiver_fax.'@myfax.com';
    $to = array('email_addr' => $recv_email_addr, 'name' => 'ebsllc');//ebsllc19
    $mail_option = array('transport' => $transport,
        'content' => $content,
        'from' => $from,
        'to' => $to);
    $return = send($mail_option);
    return $return;
}

function send($mail_option) {
    $rtn = "Success";
    set_time_limit(0);
    $mailTransport = new Zend_Mail_Transport_Smtp($mail_option['transport']['server'], $mail_option['transport']['config']);
    $mail = new Zend_Mail('utf-8');
    if ($mail_option['content']['body'])
        $mail->setBodyHtml('<b>' . $mail_option['content']['body'] . '</b>');
    if ($mail_option['content']['subject'])
        $mail->setSubject($mail_option['content']['subject']);
//$fileName = "F:\\20111001170411.pdf";
    if ($mail_option['content']['attachment']) {
        $file_path = $mail_option['content']['attachment']['file_path'];
        $file_type = $mail_option['content']['attachment']['file_type'];
        $mail->createAttachment(file_get_contents($file_path), $file_type, Zend_Mime::DISPOSITION_INLINE, Zend_Mime::ENCODING_BASE64, $file_path);
    }
    $mail->setFrom($mail_option['from']['email_addr'], $mail_option['from']['name']);
    $mail->addTo($mail_option['to']['email_addr'], $mail_option['to']['name']);
    //$mail->send($mailTransport);
    try {
        $mail->send($mailTransport);
    } catch (Exception $e) {
        //$filename = "C:\\tmp\\emailerr.txt";
        $rtn = $e->getMessage();
        //$filename = "/home2/linchen1/emailerr";
        //$err = fopen($filename, "w");
        //fwrite($err, $rtn);
        //fwrite($err, "\r\n");
        //fclose($err);
        return $rtn;
    }
    return $rtn;
}
?>
