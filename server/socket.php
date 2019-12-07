<?php
header("Content-type:text/html;charset=utf-8");

class socket {
    private $port = 8080;
    private $addr = "127.0.0.1";
    private $socket_handle;
    private $back_log = 10;
    private $websocket_key;
    private $current_message_length;

    private $is_shakehanded = false;
    private $mask_key;

    public function __construct($port = 9090, $addr = "0.0.0.0", $back_log = 10)
    {
        $this->port = $port;
        $this->addr = $addr;
        $this->back_log = $back_log;
    }

    function create(){
        $this->socket_handle=socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
        if(!$this->socket_handle){
            echo 'create fail';exit;
        } else {
            echo 'create success';
        }
    }

    function bind(){
        if(!socket_bind($this->socket_handle,$this->addr,$this->port)){
            echo socket_strerror(socket_last_error($this->socket_handle));exit;
        } else {
            echo 'listen success\n';
        }
    }

    function listen(){
        if(!socket_listen($this->socket_handle,$this->back_log)){
            echo 'error listen';
        } else {
            echo 'listen success\n';
        }
    }

    function accept(){
        $client = socket_accept($this->socket_handle);
        if(!$client){
            echo 'accept fail\n';
            exit(1);
        } else {
            while (true) {
                $bytes = socket_recv($client, $buffer, 100, 0);
                    if (!$bytes) {
                        echo 'fail\n';
                        break;
                    } else {
                        echo 'content get:' . $buffer . '\n';
                    }
            }
        }
    }

    public function start(){
        try{
            $this->create();
            $this->bind();
            $this->listen();
            $this->accept();
        } catch (Exception $e){
            echo $e->getMessage();
        }
    }
}
$s=new socket();
$s->start();
