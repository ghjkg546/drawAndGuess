# drawAndGuess
Vue加swoole websocket实现的你画我猜  
（不会前端，界面很丑，主要实现服务端,娱乐用）
已经实现：  
1.画的东西通过swoole推给所有客户端  
2.打字时，如果不是正确答案就会显示出来;是正确答案，显示xx答对了  
3.限制只能有4名玩家进入房间  
4.轮流答题   
5.设置画笔粗细，颜色  
6.发送图片（没做图片上传，只能固定几张)  
7.计分  

打算添加：一定回合后算出胜负  

使用：1.一台装过swoole、redis的Linux php服务器，运行php server.php  
2.swoole.html里改成自己的ip
3.开始
