<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/12/3
 * Time: 9:16
 */

class client{
    private $server_port;
    private $server_addr;
    private $socket_handle;

    public function __construct($port = 9090, $addr = "127.0.0.1")
    {
        $this->server_addr = $addr;
        $this->server_port = $port;
    }

    function createSocket(){
        $this->socket_handle = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
    }

    function connecToServer(){
        $this->createSocket();
        if(!socket_connect($this->socket_handle,$this->server_addr,$this->server_port)){
            echo 'connet fail';
        } else {
            while (true){
                $data=  fgets(STDIN);
                if(strcmp($data,'quit')==0){
                    break;
                }
                socket_write($this->socket_handle,$data);

            }
        }

    }

}
$client=new client();
$client->connecToServer();