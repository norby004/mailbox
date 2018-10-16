<?php

namespace Norby;

class Mailbox {

    private $options;
    private $inbox;

    function __construct($options) {
        $this->options = $options;

        if(!is_array($this->options)) die("Error: OPTIONS not validate!");

        $this->inbox = imap_open($this->options["mailbox"].$this->options["mailbox_name"], $this->options["username"], $this->options["password"]) or die("Connection failed!");
    }

    public function getAttachments($connection, $message_number) {
        $structure = imap_fetchstructure($connection, $message_number);
        $attachments = array();
        if(isset($structure->parts) && count($structure->parts)) {
            for($i = 0; $i < count($structure->parts); $i++) {
                $attachments[$i] = array(
                    'is_attachment' => false,
                    'filename' => '',
                    'name' => '',
                    'attachment' => ''
                );

                if($structure->parts[$i]->ifdparameters) {
                    foreach($structure->parts[$i]->dparameters as $object) {
                        if(strtolower($object->attribute) == 'filename') {
                            $attachments[$i]['is_attachment'] = true;
                            $attachments[$i]['filename'] = $object->value;
                        }
                    }
                }

                if($structure->parts[$i]->ifparameters) {
                    foreach($structure->parts[$i]->parameters as $object) {
                        if(strtolower($object->attribute) == 'name') {
                            $attachments[$i]['is_attachment'] = true;
                            $attachments[$i]['name'] = $object->value;
                        }
                    }
                }

                if($attachments[$i]['is_attachment']) {
                    $attachments[$i]['attachment'] = imap_fetchbody($connection, $message_number, $i+1);
                    if($structure->parts[$i]->encoding == 3) { // 3 = BASE64
                        $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                    }
                    elseif($structure->parts[$i]->encoding == 4) { // 4 = QUOTED-PRINTABLE
                        $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                    }
                }
            }
        }
        return $attachments;
    }

    public function downloadAttachments($directory, $criteria = 'ALL', $callback = NULL) {
        $emails = imap_search($this->inbox,$criteria);

        $attachments = [];

        if($emails) {
            foreach($emails as $email_number) {
                foreach($this->getAttachments($this->inbox,$email_number) as $attachment) {
                    if($attachment["is_attachment"] == TRUE) {
                        $path = $directory.DIRECTORY_SEPARATOR.$attachment["filename"];
                        file_put_contents($path, $attachment["attachment"]);
                        $attachments[] = $path;
                    }
                }
            }
        }
        if($callback !== NULL) {
            $callback($attachments);
        }
        return true;
    }

    function __destruct() {
        imap_close($this->inbox);
    }

}