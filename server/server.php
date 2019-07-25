<?php
header("Content-type:text/html;charset=utf-8");

class WebsocketDraw
{
    public $server;
    //public $colors = ['#00a1f4', '#0cc', '#f44336', '#795548', '#e91e63', '#00bcd4', '#009688', '#4caf50', '#8bc34a', '#ffc107', '#607d8b', '#ff9800', '#ff5722'];
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
        $this->redis->delete('score');
        $user_keys = $this->redis->keys('users*');
        foreach ($user_keys as $v) {
            $this->redis->delete($v);
        }
        $this->redis->set('draw_index', 0);

        $this->resetWords();
        $this->server->on('open', function (swoole_websocket_server $server, $request) {
            //$this->my_color = $this->colors[mt_rand(0, count($this->colors))];
        });
        $this->server->on('message', function (Swoole\WebSocket\Server $server, $frame) {
            //echo $frame->data;
            $data = json_decode($frame->data, true);
            $type = $data['type'];
            switch ($type) {
                case 'message':
                    //答对了
                    if (trim($data['content']) == $this->redis->get('answer')) {
                        $this->resetWords();
                        //增加分数
                        $score = $this->redis->hMGet('users:'.$data['user'], ['score']);
                        $score = current($score);
                        $score += 10;
                        $user_info = [
                            'score'=>$score
                        ];
                        $this->redis->hmSet('users:'.$data['user'], $user_info);
                        $this->sendMesToAllUser($server, $data, 1);
                    } else {
                        $this->socket_obj[$data['user']] = $frame->fd;
                        $this->sendMesToAllUser($server, $data, 0);
                    }
                    break;
                case 'bind':
                    //限制房间最大人数
                    if ($this->getUserCount() < 4) {
                        $user_info = [
                            'fd' => $frame->fd, 'name' => $data['user'], 'seat_num' => $data['seat_num'], 'score'=>0
                        ];
                        $this->redis->hmSet('users:'.$data['user'], $user_info);
                        //$this->redis->hSet('score', $frame->fd, 0);
                        $this->sendMesToAllUser($server, $data, 0, 'users');
                    } else {
                        $res_data['content'] = '房间人数已满';
                        $res_data['type'] = 'message';
                        $res_data['extra'] = '观战中';
                        $res_data['user'] = '系统';
                        $res_data['clearBoard'] = 0;
                        //$res_data['drawingUser'] = $info['drawingUser'];
                        $res_data['color'] = $this->my_color;
                        $res_data['createAt'] = date('m-d H:i:s');
                        $server->push($frame->fd, json_encode($res_data));
                    }
                    break;
                case 'change_color':
                    foreach ($server->connections as $key => $fd) {
                        $res_data['type'] = 'change_color';
                        $res_data['color'] = $data['color'];
                        $res_data['createAt'] = date('m-d H:i:s');
                        $server->push($fd, json_encode($res_data));
                    }
                    break;
                case 'pen_width':
                    foreach ($server->connections as $key => $fd) {
                        $res_data = [];
                        $res_data['type'] = 'pen_width';
                        $res_data['width'] = $data['width'];
                        $res_data['createAt'] = date('m-d H:i:s');
                        $server->push($fd, json_encode($res_data));
                    }
                    break;
                case 'line':
                    foreach ($server->connections as $key => $fd) {
                        $server->push($fd, $frame->data);
                    }
                    break;
                case 'image':
                    foreach ($server->connections as $key => $fd) {
                        $res_data['type'] = 'image';
                        $res_data['url'] = $data['url'];
                        $res_data['user'] = $data['user'];
                        $res_data['createAt'] = date('m-d H:i:s');
                        $server->push($fd, json_encode($res_data));
                    }
                    break;
                default:
                    break;
            }

        });
        $this->server->on('close', function ($ser, $fd) {
            $name = $this->getUserNameByFd($fd);
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
        $this->redis->set('answer', $this->answer);
        echo $this->answer;
    }

    public function sendMesToAllUser($server, $data, $increase_index, $type = 'message')
    {
        $user_keys = $this->redis->keys('users*');
        $keys_user = [];
        $fd_user = [];
        foreach ($user_keys as $v) {
            $user_info = !empty($this->redis->hMGet($v, ['fd', 'name', 'seat_num', 'score'])) ? $this->redis->hMGet($v, ['fd', 'name', 'seat_num', 'score']) : [];
            $keys_user[$user_info['seat_num']] = $user_info;
            $tmp_user[] = ['name' => $user_info['name'], 'fd' => $user_info['fd'], 'score' => $user_info['score']];
            $fd_user[$user_info['name']] = $user_info['fd'];
        }
        $seats = [];
        for ($i = 1; $i <= 4; $i++) {
            if (key_exists($i, $keys_user)) {
                $seats[] = ['name' => $keys_user[$i]['name']];
            } else {
                $seats[] = ['name' => '空位'];
            }
        }
        //调整当前画画人索引
        $draw_index = $this->setDrawIndex($tmp_user,$increase_index);

        foreach ($tmp_user as $k => $v) {
            if ($k == $draw_index) {
                $current_user = $v['name'];
            }
        }
        $content = $increase_index ? $data['user'] . '答对了' : (!empty($data['content']) ? $data['content'] : $data['user'] . ' 加入了游戏');
        $user = $increase_index ? '系统' : $data['user'];
        $answer = $this->redis->get('answer');
        foreach ($server->connections as $key => $fd) {
            $res_data['content'] = $content;
            $res_data['type'] = $type;
            $res_data['extra'] = array_search($fd, $fd_user) == $current_user ? "你要画的是:{$answer}" : '快点猜吧';
            $res_data['user'] = $user;
            $res_data['users'] = $tmp_user;
            $res_data['seats'] = $seats;
            $res_data['clearBoard'] = $increase_index;
            $res_data['drawingUser'] = $current_user;
            $res_data['color'] = $this->my_color;
            $res_data['createAt'] = date('m-d H:i:s');
            $server->push($fd, json_encode($res_data));
        }
    }

    public function setDrawIndex($tmp_user, $increase_index)
    {
        $user_count = count($tmp_user);
        $draw_index = $this->redis->get('draw_index');
        if ($draw_index < $user_count - 1) {
            if ($increase_index) {
                $draw_index += 1;
                $this->redis->incr('draw_index');
                $this->redis->set('answer', $this->words[$draw_index]);
            }
        } else {
            $draw_index = 0;
            $this->redis->set('draw_index', 0);
            $this->redis->set('answer', $this->words[$draw_index]);
        }
        return $draw_index;
    }

    public function getUserNameByFd($fd)
    {
        $users = !empty($this->redis->hgetall('users')) ? $this->redis->hgetall('users') : [];
        return array_search($fd, $users);
    }

    //获取当前用户数
    public function getUserCount(){
        $users = !empty($this->redis->hgetall('users')) ? $this->redis->hgetall('users') : [];
        foreach ($users as $k => $v) {
            $tmp_user[] = ['name' => $k];
        }
        return count($users);
    }
}

new WebsocketDraw();
