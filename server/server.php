<?php
header("Content-type:text/html;charset=utf-8");

class WebsocketDraw
{
    public $server;
    //public $colors = ['#00a1f4', '#0cc', '#f44336', '#795548', '#e91e63', '#00bcd4', '#009688', '#4caf50', '#8bc34a', '#ffc107', '#607d8b', '#ff9800', '#ff5722'];
    public $my_color;
    public $words = ['apple', 'banana', 'potato'];
    public $answer = '';
    public $socket_obj = [];
    public $redis = [];
    public $draw_index = 0; //当前画画人的索引
    public $remain_time = 30;
    public $max_remain_time = 30;
    public $user_count = 0;
    public $win_score = 20;

    public function __construct()
    {
        $this->server = new Swoole\WebSocket\Server("0.0.0.0", 9501);
        $this->server->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
        $this->redis->delete('score');
        $this->redis->set('user_count', 0);
        $this->redis->set('timer', $this->remain_time);
        $user_keys = $this->redis->keys('users*');
        foreach ($user_keys as $v) {
            $this->redis->delete($v);
        }
        $this->initGame();
        $this->server->on('open', function (swoole_websocket_server $server, $request) {
            $this->onEnterRoom();
        });
        $this->server->on('message', function (Swoole\WebSocket\Server $server, $frame) {
            $data = json_decode($frame->data, true);
            $type = $data['type'];
            switch ($type) {
                case 'message':
                    //答对了
                    if (trim($data['content']) == $this->redis->get('answer')) {
                        //刷新绘画关键词
                        $this->resetWords();
                        //增加分数
                        $this->increaseUserScore($frame->fd);
                        //推送用户答对消息
                        $this->pushAnswerCorrectMessage($server, $data);
                    } else {
                        foreach ($server->connections as $key => $fd) {
                            $res_data['content_type'] = 'text';
                            $res_data['type'] = 'message';
                            $res_data['content_user'] = $data['user'];
                            $res_data['user'] = $data['user'];
                            $res_data['content'] = $data['content'];
                            $res_data['createAt'] = date('m-d H:i:s');
                            $server->push($fd, json_encode($res_data));
                        }
                    }
                    break;
                case 'bind':
                    //限制房间最大人数
                    if ($this->getUserCount() < 4) {
                        $this->redis->hSet('username_for_fd',$data['user'],$frame->fd);
                        $user_info = [
                            'fd' => $frame->fd, 'name' => $data['user'], 'seat_num' => $data['seat_num'], 'score' => 0
                        ];
                        $this->redis->hSet('user_info',$frame->fd,json_encode($user_info));
                        //推送新的分数
                        $this->pushUserList($server, $data['user'].'加入了游戏');
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
                case 'startgame':
                    //检测人数是否足够
                    if ($this->getUserCount() < 2) {
                        foreach ($server->connections as $key => $fd) {
                            $res_data['content_type'] = 'text';
                            $res_data['type'] = 'message';
                            $res_data['content_user'] = '系统';
                            $res_data['content'] = '至少需要2人才能开始游戏';
                            $res_data['createAt'] = date('m-d H:i:s');
                            $server->push($fd, json_encode($res_data));
                        }
                    } else {
                        //开始游戏
                        $draw_index = $this->redis->get('draw_index');
                        $answer = $this->redis->get('answer');
                        $this->redis->set('remain_time', $this->max_remain_time);
                        $user_info = $this->redis->hGetAll('user_info');
                        $draw_user = [];
                        $i = 0;
                        foreach ($user_info as $k => $v) {
                            if ($i == $draw_index) {
                                $draw_user = json_decode($v, true);
                            }
                            $i++;
                        }
                        foreach ($server->connections as $key => $fd) {
                            $res_data['extra'] = $fd == $draw_user['fd'] ? "你要画的是:{$answer}" : '快点猜吧';
                            $res_data['content'] = '游戏开始了';
                            $res_data['type'] = 'startgame';
                            $res_data['drawingUser'] = $draw_user['name'];
                            $res_data['createAt'] = date('m-d H:i:s');
                            $server->push($fd, json_encode($res_data));
                        }
                        $this->setGameStatus('playing');
                        $this->gameLoop();
                    }


                    break;
                case 'restartgame':
                    $draw_index = $this->redis->get('draw_index');
                    $answer = $this->redis->get('answer');
                    $this->redis->set('remain_time', $this->max_remain_time);
                    $user_info = $this->redis->hGetAll('user_info');
                    $draw_user = [];
                    $i = 0;
                    foreach ($user_info as $k => $v) {
                        if ($i == $draw_index) {
                            $draw_user = json_decode($v, true);
                        }
                        $i++;
                    }
                    foreach ($server->connections as $key => $fd) {
                        $res_data['extra'] = $fd == $draw_user['fd'] ? "你要画的是:{$answer}" : '快点猜吧';
                        $res_data['content'] = '游戏开始了';
                        $res_data['type'] = 'startgame';
                        $res_data['drawingUser'] = $draw_user['name'];
                        $res_data['createAt'] = date('m-d H:i:s');
                        $server->push($fd, json_encode($res_data));
                    }
                    //刷新分数等
                    $this->pushUserList($server,'游戏开始了');
                    break;
                default:
                    break;
            }

        });
        $this->server->on('close', function ($ser, $fd) {
            $user_name = $this->getUserInfoByFd($fd)['name'];
            $this->redis->hDel('user_info', $fd);
            $this->pushUserList($this->server,$user_name.'离开了游戏');
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

    //初始化游戏 重置信息
    public function initGame()
    {
        $this->redis->set('draw_index', 0);
        $this->redis->set('remain_time', null);
        $this->redis->del('user_info');

        $this->setGameStatus('wait');
    }

    public function setGameStatus($status)
    {
        $this->redis->set('status', $status);
    }

    public function changeRemainTime($num)
    {
        $this->redis->incr('remain_time', $num);
    }

    //获取用户数量
    public function getUserCount()
    {
        return count($this->redis->hGetAll('user_info'));
    }

    //开始游戏循环
    public function gameLoop()
    {

        $this->remain_time = $this->redis->get('remain_time');
        if (!empty($this->remain_time)) {
            Swoole\Timer::tick(1000, function () {

                $tmp_time = $this->redis->get('remain_time');
                if (!empty($tmp_time)) {
                    //if($this->redis->get('status') != 'wait'){

                    $this->changeRemainTime(-1);
                    $this->remain_time = $tmp_time;
                    $user_keys = $this->redis->keys('users*');
                    $keys_user = [];
                    $fd_user = [];
                    $tmp_user = [];
                    foreach ($user_keys as $v) {
                        $user_info = !empty($this->redis->hMGet($v, ['fd', 'name', 'seat_num', 'score'])) ? $this->redis->hMGet($v, ['fd', 'name', 'seat_num', 'score']) : [];
                        $keys_user[$user_info['seat_num']] = $user_info;
                        $tmp_user[] = ['name' => $user_info['name'], 'fd' => $user_info['fd'], 'score' => $user_info['score']];
                        $fd_user[$user_info['name']] = $user_info['fd'];
                    }
                    $current_user = '';

                    if ($this->remain_time > 1) {
                        //echo '剩余时间：'.$this->remain_time;
                        foreach ($this->server->connections as $key => $fd) {
                            $res_data['content'] = 'aa';
                            $res_data['type'] = 'timer';
                            $res_data['remain_time'] = $this->remain_time;
                            $res_data['user'] = $tmp_user;
                            $res_data['color'] = $this->my_color;
                            $res_data['createAt'] = date('m-d H:i:s');
                            $this->server->push($fd, json_encode($res_data));
                        }
                    } else {
                        $this->resetWords();
                        $this->redis->set('remain_time', $this->max_remain_time);
                        $this->remain_time = $this->redis->get('remain_time');
                        $draw_index = $this->setDrawIndex(1);
                        $answer = $this->redis->get('answer');
                        foreach ($tmp_user as $k => $v) {
                            if ($k == $draw_index) {
                                $current_user = $v['name'];
                            }
                        }
                        foreach ($this->server->connections as $key => $fd) {
                            $res_data['extra'] = array_search($fd, $fd_user) == $current_user ? "你要画的是:{$answer}" : '快点猜吧';
                            $res_data['content'] = '时间到，下一位';
                            $res_data['type'] = 'startgame';
                            $res_data['remain_time'] = $this->remain_time;
                            $res_data['user'] = $tmp_user;
                            $res_data['drawingUser'] = $current_user;
                            $res_data['color'] = $this->my_color;
                            $res_data['createAt'] = date('m-d H:i:s');
                            $this->server->push($fd, json_encode($res_data));
                        }
                    }
                }

            });
        }
    }

    public function onWorkerStart($server, $worker_id)
    {
        // 在Worker进程开启时绑定定时器
        echo "onWorkerStart\n";
        // 只有当worker_id为0时才添加定时器,避免重复添加
        if ($worker_id == 0) {
            $this->gameLoop();
        }
    }

    //当玩家进入房间
    public function onEnterRoom()
    {
        $server = $this->server;
        $user_info_list = $this->redis->hGetAll('user_info');
        foreach ($server->connections as $key => $fd) {
            $res_data['content'] = '';
            $res_data['type'] = 'enter_room';
            $res_data['users'] =$this->getAllUserInfo($user_info_list);
            $res_data['seats'] = $this->getSeatUser($user_info_list);
            $res_data['color'] = $this->my_color;
            $res_data['createAt'] = date('m-d H:i:s');
            $server->push($fd, json_encode($res_data));
        }
    }

    //获取玩家的得分等信息
    public function getAllUserInfo($user_info_list){
        $info = [];
        foreach ($user_info_list as $v){
            $info[] = json_decode($v,true);
        }
        return $info;
    }

    //获取座位上的玩家信息
    public function getSeatUser($user_info_list){
        $keys_user = [];
        foreach ($user_info_list as $v) {
            $user_info = json_decode($v, true);
            $keys_user[$user_info['seat_num']] = $user_info;
        }
        $seats = [];
        for ($i = 1; $i <= 4; $i++) {
            if (key_exists($i, $keys_user)) {
                $seats[] = ['name' => $keys_user[$i]['name']];
            } else {
                $seats[] = ['name' => '空位'];
            }
        }
        return $seats;
    }

    //当游戏结束
    public function onEndGame($winner)
    {
        $this->redis->set('remain_time', null);
        $server = $this->server;
        foreach ($server->connections as $key => $fd) {
            $res_data['content'] = '';
            $res_data['type'] = 'end_game';
            $res_data['winner'] = $winner;
            $res_data['createAt'] = date('m-d H:i:s');
            $server->push($fd, json_encode($res_data));
        }

    }

    //重设答案
    public function resetWords()
    {
        $this->answer = $this->words[mt_rand(0, count($this->words) - 1)];
        $this->redis->set('answer', $this->answer);

        echo $this->answer;
    }

    //获取用户fd
    public function getUserFds()
    {
        $fd_user = [];
        $user_keys = $this->redis->keys('users*');
        foreach ($user_keys as $v) {
            $user_info = !empty($this->redis->hMGet($v, ['fd', 'name', 'seat_num', 'score'])) ? $this->redis->hMGet($v, ['fd', 'name', 'seat_num', 'score']) : [];
            $keys_user[$user_info['seat_num']] = $user_info;
            $tmp_user[] = ['name' => $user_info['name'], 'fd' => $user_info['fd'], 'score' => $user_info['score']];
            $fd_user[$user_info['name']] = $user_info['fd'];
        }
        return $fd_user;
    }

    //推送新的用户列表
    public function pushUserList($server, $content){
        $user_info_list = $this->redis->hGetAll('user_info');

        foreach ($server->connections as $key => $fd) {
            $res_data['content'] = $content;
            $res_data['type'] = 'users';
            $res_data['content_type'] = 'text';
            //$res_data['user'] = $user;
            $res_data['users'] = $this->getAllUserInfo($user_info_list);
            $res_data['seats'] = $this->getSeatUser($user_info_list);
            $res_data['clearBoard'] = 1;
            $res_data['color'] = $this->my_color;
            $res_data['createAt'] = date('m-d H:i:s');

            $server->push($fd, json_encode($res_data));
        }
    }

    //推送答对消息
    public function pushAnswerCorrectMessage($server, $data)
    {
        $keys_user = [];
        $fd_user = [];
        $tmp_user = [];
        $user_info_list = $this->redis->hGetAll('user_info');
        foreach ($user_info_list as $v) {
            $user_info = json_decode($v, true);
            $keys_user[$user_info['seat_num']] = $user_info;
            $tmp_user[] = ['name' => $user_info['name'], 'fd' => $user_info['fd'], 'score' => $user_info['score']];
            $fd_user[$user_info['name']] = $user_info['fd'];
        }
        //$tmp_user = array_reverse($tmp_user);

        $content = $data['user'] . '答对了';

        $this->redis->set('remain_time', $this->max_remain_time);
        $current_user = '';
        $draw_index = $this->setDrawIndex(1);

        $draw_user = [];
        $i = 0;
        foreach ($user_info_list as $k=>$v){
            if ($i == $draw_index) {
                $draw_user = json_decode($v, true);
            }
            $i++;
        }
        foreach ($tmp_user as $k => $v) {
            if ($k == $draw_index) {
                $current_user = $v['name'];
            }
        }
        $correct_user = [];
        foreach ($tmp_user as $v) {
            if ($v['name'] == $data['user']) {
                $correct_user = $v;
            }
        }
        $answer = $this->redis->get('answer');
        foreach ($server->connections as $key => $fd) {
            $res_data['content'] = $content;
            $res_data['type'] = 'change_score';
            $res_data['extra'] = $draw_user['fd']==$fd ? "你要画的是:{$answer}" : '快点猜吧';
            $res_data['content_type'] = 'text';
            $res_data['content_user'] = '系统';
            $res_data['drawingUser'] = $current_user;
            $res_data['user'] = $correct_user['name'];
            $res_data['score'] = $correct_user['score'];
            $res_data['clearBoard'] = 1;
            $res_data['createAt'] = date('m-d H:i:s');

            $server->push($fd, json_encode($res_data));
        }
        //检测是否结束
        foreach ($tmp_user as $v) {
            if ($v['score'] >= $this->win_score) {
                //$this->initGame();
                $this->onEndGame($v['name']);
                //return false;
            }
        }
    }

    //给所有用户发消息
    public function sendMesToAllUser($server, $data, $increase_index, $type = 'message')
    {
        $keys_user = [];
        $fd_user = [];
        $tmp_user = [];
        $user_info_list = $this->redis->hGetAll('user_info');
        foreach ($user_info_list as $v) {
            $user_info = json_decode($v, true);
            $keys_user[$user_info['seat_num']] = $user_info;
            $tmp_user[] = ['name' => $user_info['name'], 'fd' => $user_info['fd'], 'score' => $user_info['score']];
            $fd_user[$user_info['name']] = $user_info['fd'];
        }
        $tmp_user = array_reverse($tmp_user);
        $seats = [];
        for ($i = 1; $i <= 4; $i++) {
            if (key_exists($i, $keys_user)) {
                $seats[] = ['name' => $keys_user[$i]['name']];
            } else {
                $seats[] = ['name' => '空位'];
            }
        }

        $content = $increase_index ? $data['user'] . '答对了' : (!empty($data['content']) ? $data['content'] : $data['user'] . ' 加入了游戏');
        if ($increase_index) {
            $this->redis->set('remain_time', $this->max_remain_time);
        }
        $type = $increase_index ? 'change_score' : $type;
        $user = $increase_index ? '系统' : $data['user'];
        $current_user = '';
        $draw_index = 0;
        if ($type == 'change_score') {
            $draw_index = $this->setDrawIndex(1);
            foreach ($tmp_user as $k => $v) {
                if ($k == $draw_index) {
                    $current_user = $v['name'];
                }
            }
        }
        $correct_user = [];
        foreach ($tmp_user as $v) {
            if ($v['name'] == $data['user']) {
                $correct_user = $v;
            }
        }
        $answer = $this->redis->get('answer');
        foreach ($server->connections as $key => $fd) {
            if ($type == 'change_score') {
                $res_data['content'] = $content;
                $res_data['type'] = $type;
                $res_data['extra'] = $key == ($draw_index + 1) ? "你要画的是:{$answer}" : '快点猜吧';
                $res_data['content_type'] = 'text';
                $res_data['content_user'] = '系统';
                $res_data['drawingUser'] = $current_user;
                $res_data['user'] = $correct_user['name'];
                $res_data['score'] = $correct_user['score'];
                $res_data['seats'] = $seats;
                $res_data['clearBoard'] = $increase_index;
                $res_data['createAt'] = date('m-d H:i:s');
            } else {
                $res_data['content'] = $content;
                $res_data['type'] = $type;
                $res_data['content_type'] = 'text';
                $res_data['user'] = $user;
                $res_data['users'] = $tmp_user;
                $res_data['seats'] = $seats;
                $res_data['clearBoard'] = $increase_index;
                $res_data['color'] = $this->my_color;
                $res_data['createAt'] = date('m-d H:i:s');
            }

            $server->push($fd, json_encode($res_data));
        }
        //检测是否结束
        foreach ($tmp_user as $v) {
            if ($v['score'] >= $this->win_score) {
                $this->initGame();
                $this->onEndGame($v['name']);
                return false;
            }
        }
    }

    public function setDrawIndex($increase_index)
    {
        $user_count = $this->getUserCount();
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
        echo 'index:'.$draw_index;
        return $draw_index;
    }

    public function getFdByUserName($user_name){
        return $this->redis->hGet('username_for_fd',$user_name);
    }

    public function getUserInfoByFd($fd){
        return json_decode($this->redis->hGet('user_info',$fd),1);
    }

    //增加用户分数
    public function increaseUserScore($fd)
    {
        $user_info = json_decode($this->redis->hGet('user_info', $fd),true);
        $score = $user_info['score'] + 10;
        $user_info['score'] = $score;
        $this->redis->hSet('user_info', $fd, json_encode($user_info));
    }

    public function getUserNameByFd($fd)
    {
        $users = !empty($this->redis->hgetall('users')) ? $this->redis->hgetall('users') : [];
        return array_search($fd, $users);
    }


}

new WebsocketDraw();
