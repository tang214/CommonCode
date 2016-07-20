<?php
require_once('Autoload.php');
class EmailTest extends PHPUnit_Framework_TestCase
{
    public function testEmail()
    {
        $email = new \Email\Email();
        $this->assertEquals('Burning Flipside <webmaster@burningflipside.com>', $email->getFromAddress());
        $email->setFromAddress('test@example.com');
        $this->assertEquals('test@example.com', $email->getFromAddress());
        $email->setFromAddress('test@example.com', 'Test User');
        $this->assertEquals('Test User <test@example.com>', $email->getFromAddress());

        $this->assertEmpty($email->getToAddresses());
        $this->assertEmpty($email->getCCAddresses());
        $this->assertEmpty($email->getBCCAddresses());
        $this->assertEquals('Test User <test@example.com>', $email->getReplyTo());
        $this->assertFalse($email->getSubject());
        $this->assertEmpty($email->getHTMLBody());
        $this->assertEmpty($email->getTextBody());
        $this->assertFalse($email->hasAttachments());

        $email->setReplyTo('not-test@example.com', 'Not Test User');
        $this->assertEquals('Not Test User <not-test@example.com>', $email->getReplyTo());

        $email->addToAddress('me@me.com', 'Me');
        $this->assertEquals($email->getToAddresses(), array('Me <me@me.com>'));

        $email->addCCAddress('cc@me.com', 'Me');
        $this->assertEquals($email->getCCAddresses(), array('Me <cc@me.com>'));

        $email->addBCCAddress('bcc@me.com', 'Me');
        $this->assertEquals($email->getBCCAddresses(), array('Me <bcc@me.com>'));

        $email->setSubject('Test Subject');
        $this->assertEquals($email->getSubject(), 'Test Subject');

        $this->assertEquals($email->encodeRecipients('test@test.com'), 'test@test.com');
        
        $res = $email->encodeRecipients('Test User <test@test.com>');
        $this->assertEquals($res, '=?UTF-8?B?VGVzdCBVc2VyIA==?= <test@test.com>');

        $res = $email->encodeRecipients(array('test@test.com', 'me@me.com'));
        $this->assertEquals($res, 'test@test.com, me@me.com');

        $res = $email->encodeRecipients(array('Test User <test@test.com>', 'me@me.com'));
        $this->assertEquals($res, '=?UTF-8?B?VGVzdCBVc2VyIA==?= <test@test.com>, me@me.com');

        $res = $email->encodeRecipients(array('Test User <test@test.com>', 'Bob Smith <me@me.com>'));
        $this->assertEquals($res, '=?UTF-8?B?VGVzdCBVc2VyIA==?= <test@test.com>, =?UTF-8?B?Qm9iIFNtaXRoIA==?= <me@me.com>');
    }

    public function testEmailService()
    {
        $service = new \Email\EmailService(false);
        $this->assertFalse($service->canSend());
        $this->assertFalse($service->sendEmail(false));
    }
}
/* vim: set tabstop=4 shiftwidth=4 expandtab: */
