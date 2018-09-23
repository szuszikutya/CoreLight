<?PHP

	define ("EOL", "\r\n");

class Core_Mail
{
    public $charset = 'UTF-8';
    public $mode = 'html';
    public $sMsg = "";
    public $aBoundary = array ();
    public $isLastElement = false;

    protected $attacheFile = array ();
    protected $attacheContent = array ();
    protected $subject = '(No Subject)';
    protected $To = null;
    protected $From = null;
    protected $Message = null;
    protected $Message_Plain = null;
    protected $Message_Header = array ();
    protected $UID = null;
    protected $schema = "";
    protected $server_report = array ();
    protected $server_response = "";

    public function __construct ()
    {
        $this->To = array ();
        if (!empty(core::$_CONFIG ['SMTP'])) {
            foreach (core::$_CONFIG ['SMTP'] as $key => $value)
            {
                if ($key == 'SmtpUser' || $key == 'SmtpPass') $value = base64_encode ($value);
                if (substr ($key,0,4) == 'Smtp') $this->$key = $value;
            }
        }
    }

    public function setSubject ($text) { $this->subject = $text; }

    public function addTo ($mail = null, $name = null)
    {
        if ( $mail == null ) return false;
        $this->To[] = (object) array ('name' => trim ($name), 'mail' => trim ($mail) );
        return true;
    }

    public function setFrom ($mail = null, $name = null)
    {
        if ( $mail == null ) return false;
        $this->From = (object) array ('name' => trim ($name), 'mail' => trim ($mail) );
        return true;
    }

    public function setBodyHtml ($msg)
    {
        $msg = str_replace ("\r", "", $msg);
        $msg = str_replace ("\n", "#EOL#", $msg);
        $msg = str_replace ("#EOL#", EOL, $msg);

        $msg = rtrim($msg);

        $plain_content = explode (EOL, str_replace (array ('<br>', '<br />', '<BR>', '<BR />'), EOL, strip_tags ($msg, '<br>')));
        $rela_content = array ();
        foreach ($plain_content as $line)
        {
            $line = trim ($line);
            if (!empty ($line)) array_push ($rela_content, $line);
        }
        $plain_content = null;
        $this->Message = $msg;
        $this->Message_Plain = join (EOL, $rela_content);
    }

    public function AddAttach ($file)
    {
        $file = trim ($file);
        if (!is_file ($file) ) { echo "Missing File:$file\n"; return false; }
        array_push ($this->attacheFile,  $file );
        return true;
    }

    public function Send ()
    {
        $report = array ();
        for ($i=0; $i<count ($this->To); $i++ )
        {
            $to = (!empty ($this->To [$i]->name)) ? $this->ConvertToSafeText ($this->To [$i]->name)." <{$this->To [$i]->mail}>" : "<{$this->To [$i]->mail}>";
            $this->Create_Message_Header ($to);
            $response = $this->SMTP_Send ($this->To [$i]->mail );
            if (empty ($response)) {
                $message = $this->Create_Message_Body ();
                $report [] = mail ($to, $this->subject, $message.EOL.$this->sMsg, join(EOL, $this->Message_Header), "-f ".$this->From->mail);
            } else {
                $report [] = $response;
            }
        }
        return $report;
    }

    protected function ConvertToSafeText ($string = null)
    {
        return "=?UTF-8?B?".base64_encode ((empty ($string)) ? $this->subject : $string)."?=";
    }

    protected function Get_Text_Content ($uid = null)
    {
        $uid_msg = ($uid == null) ? md5 (uniqid (time ())) : $uid;
        $return = array (
            "Content-Type" => "multipart/alternative; boundary=\"{$uid_msg}\"",
            "boundary" => $uid_msg,
            "contents" => array (
                array (
                    "Content-Type" =>  "text/plain; charset=\"utf-8\"; format=\"fixed\"",
                    "Content-Transfer-Encoding" => "8bit",
                    "contents" => &$this->Message_Plain
                ),
                array (
                    "Content-Type" =>  "text/html;  charset=\"utf-8\"; format=\"fixed\"",
                    "Content-Transfer-Encoding" => "8bit",
                    "contents" => &$this->Message
                )
            )
        );
        return $return;
    }

    protected function Create_Message_Header ($recipient_to = null)
    {
        $this->UID = "_-------=_".md5 (uniqid (time ()));
        $sender = (!empty ($this->From->name)) ? $this->ConvertToSafeText ($this->From->name)." <{$this->From->mail}>" : "<{$this->From->mail}>";

        if (count ($this->attacheFile) > 0) {
            $this->schema = array (
                "Content-Type" => "multipart/mixed; boundary=\"{$this->UID}\"",
                "boundary" => $this->UID,
                "contents" => array ()
            );

            array_push ($this->schema ['contents'], $this->Get_Text_Content ());

            for ($j=0; $j<count ($this->attacheFile); $j++ )
            {
                $file_size = filesize ($this->attacheFile [$j]);
                $handle = fopen ($this->attacheFile [$j], "r");
                array_push ($this->schema ['contents'], array (
                    "Content-Type" => "application/octet-stream; name=\"{$this->attacheFile[$j]}\"",
                    "Content-Transfer-Encoding" => "base64",
                    "Content-Disposition" =>  "attachment; filename=\"{$this->attacheFile[$j]}\"",
                    "contents" => chunk_split (base64_encode (fread ($handle, $file_size)))
                ));
                fclose ($handle);
            }
        } else {
            $this->schema = $this->Get_Text_Content ($this->UID);
        }

        $this->Message_Header = array ();
        array_push ($this->Message_Header, "MIME-Version: 1.0");
        array_push ($this->Message_Header, "Content-Type: ".$this->schema['Content-Type']);
        array_push ($this->Message_Header, "X-Mailer: Core-Mailer");
        array_push ($this->Message_Header, "Date: ".date("r"));
        array_push ($this->Message_Header, "From: {$sender}");
        array_push ($this->Message_Header, "Return-Path: {$sender}");
        array_push ($this->Message_Header, "Subject: ".$this->ConvertToSafeText ($this->subject));
        if ($recipient_to != null) array_push ($this->Message_Header, "To: ".$recipient_to);
    }

    protected function Create_Message_Body ()
    {
        $this->sMsg = "";
        $this->aBoundary = array ();
        $this->isLastElement = false;
        $this->Build_Content ($this->schema);

        $data = join (EOL, $this->Message_Header);
        $data .= EOL.EOL."This is a multi-part message in MIME format".EOL;

        return $data;
    }

    protected function _read_socked ()
    {
        $this->server_response = fread ($this->socket, 1024);
        array_push ($this->server_report, "*".$this->server_response);
    }

    protected function server_script ($command_script = null)
    {
        foreach ($command_script as $command)
        {
            $expected_response = substr ($command,0,3);
            array_push ($this->server_report, ">".substr ($command,3));
            fwrite ($this->socket, substr ($command,3).EOL );
            $this->_read_socked ();

            if ( !(substr ($this->server_response, 0, 3) == $expected_response)) {
                array_push ($this->server_report, "Unable to send e-mail. Please contact the forum administrator with the following error message reported by the SMTP server: \"{$this->server_response}\"");
                return false;
            }
        }
        return true;
    }

    protected function SMTP_Send ($to_mail)
    {
        ini_set ('memory_limit', '64M');
        $this->socket = fsockopen ($this->SmtpServer, $this->SmtpPort, $errno, $errstr, 15);

        if (!$this->socket) {
            array_push ($this->server_report, "Could not connect to smtp host \"{$this->SmtpServer}\" ({$errno}) ({$errstr})");
        } else {
            $this->_read_socked ();
            if (substr ($this->server_response,0,3) != "220") {
                array_push ($this->server_report, "Couldn\'t get mail server response codes. Please contact the forum administrator.");
            } else {
                $server = @$this->SmtpServer;
                $ok = $this->server_script (array ("250EHLO {$server}", "334AUTH LOGIN", "334{$this->SmtpUser}", "235{$this->SmtpPass}", "250MAIL FROM: <{$this->From->mail}>", "250RCPT TO: <{$to_mail}>", "354DATA"));

                if ($ok === true) {
                    $data = $this->Create_Message_Body ();
                    $data .= EOL.$this->sMsg;
                    fwrite ($this->socket, $data.EOL.EOL.EOL.".".EOL );
                    $this->_read_socked ();
                    if (substr($this->server_response,0,3) != "250") {
                        array_push ($this->server_report, "send error:".$this->server_response);
                    }
                    $this->server_script (array ('221QUIT'));
                    fclose($this->socket);
                }
            }
        }
        return $this->server_report;
    }

    protected function Build_Content ($content = array ())
    {
        if (empty ($content)) return;
        $isRoot = false; $isNewSection = false;
        if (isset ($content ['boundary'])) {
            if (empty ($this->aBoundary)) {
                $isRoot = true;
            } else {
                $isNewSection = true;
            }
            array_push ($this->aBoundary, $content ['boundary']);
        }
        if (count($this->aBoundary)>0 && !$isRoot) {
            $section = ($isNewSection) ? $this->aBoundary [count ($this->aBoundary)-2] : $this->aBoundary [count ($this->aBoundary)-1];
            $this->sMsg .= EOL."--".$section.EOL;
        }

        if (!$isRoot) {
            foreach ($content as $key => $value)
            {
                if ($key == 'boundary' || $key == 'contents') continue;
                $this->sMsg .= $key.': '.$value.EOL;
            }
        }

        if (isset($content ['contents'])) {
            if (is_array ($content ['contents'])) {
                for ($i=0; $i<count ($content ['contents']); $i++)
                {
                    $this->isLastElement = ($i == count ($content ['contents'])-1) ? true : false;
                    $this->Build_Content ($content ['contents'][$i]);
                }
            } else {
                $this->sMsg .= EOL.rtrim ($content ['contents']).EOL;
            }
        }

        if (count ($this->aBoundary) > 0 && ($isRoot || $this->isLastElement)) {
            if (($this->isLastElement && count($this->aBoundary)>1) || $isRoot) $this->sMsg .= '--'.array_pop ($this->aBoundary).'--'.EOL;
        }
    }
}