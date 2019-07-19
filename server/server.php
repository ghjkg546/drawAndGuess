<?php
header("Content-type:text/html;charset=utf-8");

class WebsocketTest
{
    public $server;
    public $colors = ['#00a1f4', '#0cc', '#f44336', '#795548', '#e91e63', '#00bcd4', '#009688', '#4caf50', '#8bc34a', '#ffc107', '#607d8b', '#ff9800', '#ff5722'];
    public $my_color;
    public $words = ['飞机', '苹果', '太阳'];
    public $answer = '';
    public $socket_obj = [];
    public $redis = [];
    public $draw_index = 0; //当前画画人的索引

    public function __construct()
    {
        $this->server = new Swoole\WebSocket\Server("0.0.0.0", 9501);
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
        $this->redis->delete('users');
        $this->redis->set('draw_index', 0);
        $this->resetWords();
        $this->server->on('open', function (swoole_websocket_server $server, $request) {
            $this->my_color = $this->colors[mt_rand(0, count($this->colors))];
        });
        $this->server->on('message', function (Swoole\WebSocket\Server $server, $frame) {
            //echo $frame->data;
            $data = json_decode($frame->data, true);
            $type = $data['type'];
            if ($type == 'message') {
                //答对了
                if (trim($data['content']) == $this->answer) {

                    $this->resetWords();
                    $this->sendMesToAllUser($server, $data, 1);
                } else {
                    $this->socket_obj[$data['user']] = $frame->fd;
                    //echo "receive from {$frame->fd}:{$frame->data}\n";
                    //$server->push($frame->fd, "this is server");
                    $this->sendMesToAllUser($server, $data, 0);
                }
            }/* elseif ($type == 'startgame') {
                $users = !empty($this->redis->hgetall('users')) ? $this->redis->hgetall('users') : [];
                foreach ($users as $k => $v) {
                    $tmp_user[] = ['name' => $k];
                }
                $user_count = count($tmp_user);
                self::$draw_index = 0;
                foreach ($tmp_user as $k => $v) {
                    if ($k == self::$draw_index) {
                        $current_user = $v['name'];
                    }
                }
            }*/ elseif ($type == 'bind') {
                //限制房间最大人数
                if ($info['count'] < 4) {
                    $this->redis->hSet('users', $data['user'], $frame->fd);
                    $info = $this->sendMesToAllUser($server, $data, 0, 'users');
                } else {
                    $res_data['content'] = '房间人数已满';
                    $res_data['type'] = 'message';
                    $res_data['extra'] = '观战中';
                    $res_data['user'] = '系统';
                    $res_data['clearBoard'] = 0;
                    $res_data['drawingUser'] = $info['drawingUser'];
                    $res_data['color'] = $this->my_color;
                    $res_data['createAt'] = date('m-d H:i:s');
                    $server->push($frame->fd, json_encode($res_data));
                }
            }

            if ($data['type'] == 'line') {

                $this->socket_obj[$data['user']] = $frame->fd;
                foreach ($server->connections as $key => $fd) {
                    $server->push($fd, $frame->data);
                }
            }


            if ($data['type'] == 'pic') {
                foreach ($server->connections as $key => $fd) {
                    $res_data['type'] = 'pic';
                    $res_data['pic_url'] = $data['pic_url'];
                    $res_data['user'] = $data['user'];
                    $res_data['createAt'] = date('m-d H:i:s');
                    $server->push($fd, json_encode($res_data));
                }
            }

        });
        $this->server->on('close', function ($ser, $fd) {
            $name = $this->getUserNameByFd($fd);
            echo $name;
            $this->redis->hDel('users', $name);
            echo "client {$fd} closed\n";
        });
        $this->server->on('request', function ($request, $response) {
            // 接收http请求从get获取message参数的值，给用户推送
            // $this->server->connections 遍历所有websocket连接用户的fd，给所有用户推送
            foreach ($this->server->connections as $fd) {
                $this->server->push($fd, $request->get['message']);
            }
        });
        $this->server->start();
    }

    public function resetWords()
    {
        $this->answer = $this->words[mt_rand(0, count($this->words) - 1)];
        echo $this->answer;
    }

    public function sendMesToAllUser($server, $data, $increse_index, $type = 'message')
    {
        $users = !empty($this->redis->hgetall('users')) ? $this->redis->hgetall('users') : [];
        foreach ($users as $k => $v) {
            $tmp_user[] = ['name' => $k];
        }
        $user_count = count($tmp_user);
        echo 'user_count:' . $user_count;
        $draw_index = $this->redis->get('draw_index');
        if ($draw_index < $user_count - 1) {
            if ($increse_index) {
                $draw_index += 1;
                $this->redis->incr('draw_index');
            }
        } else {
            $draw_index = 0;
            $this->redis->set('draw_index', 0);
        }
        foreach ($tmp_user as $k => $v) {
            if ($k == $draw_index) {
                $current_user = $v['name'];
            }
        }
        $content = $increse_index ? $data['user'] . '答对了' : (!empty($data['content']) ? $data['content'] : $data['user'] . ' 加入了游戏');
        $user = $increse_index ? '系统' : $data['user'];
        foreach ($server->connections as $key => $fd) {
            $res_data['content'] = $content;
            $res_data['type'] = $type;
            $res_data['extra'] = array_search($fd, $users) == $current_user ? "你要画的是:{$this->answer}" : '快点猜吧';
            $res_data['user'] = $user;
            $res_data['users'] = $tmp_user;
            $res_data['clearBoard'] = $increse_index;
            $res_data['drawingUser'] = $current_user;
            $res_data['color'] = $this->my_color;
            $res_data['createAt'] = date('m-d H:i:s');
            $server->push($fd, json_encode($res_data));
        }
        if ($type == 'users') {
            return ['count' => count($tmp_user), 'drawingUser' => $current_user];
        }
    }

    public function getUserNameByFd($fd)
    {
        $users = !empty($this->redis->hgetall('users')) ? $this->redis->hgetall('users') : [];
        return array_search($fd, $users);
    }
}

new WebsocketTest();
