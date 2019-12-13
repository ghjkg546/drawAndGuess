// 监听与服务端的连接
let gameObj = {
    isDrawing: false,
    startX: 0,
    startY: 0,
    WATTING: 0,
    START: 1,
    OVER: 2,
    RESTART: 3,
    isPlayer: false
};

const LINE = 0;
const MESSAGE = 1;
const GAME = 2;
const ANSWER = 3;
const GETANSWER = 4;
const GETSOCRE = 5;


// 重玩
$("#restart").on("click", function () {
    let data = {};
    data.state = gameObj.RESTART;
    data.type = GAME;
    that.socket.send(JSON.stringify(data));

    $(this).hide();
});



function startGame() {
    console.log("send")
    let value = $.trim($("#input").val());
    if (value !== "") {
        let data = {};
        data.type = "message";
        data.message = value;

        that.socket.send(JSON.stringify(data));
        $("#input").val("");
    }


}


var header = new Vue({
    el: "#app",
    data: {
        scoreList: [{'name':'系统','content':'欢迎进入游戏','content_type':'text'}],
        users: [],
        explain: "",
        caohaogan: 0,
        show_btn: false,
        currentIndex: 1,
        options: [1, 2, 3, 4],
        rightAnswer: 1,
        questions: [],
        answers: [],
        canAnswer: 1,
        showColor: 0,
        showNextQues: 0,
        sendText: "",
        drawingUser: '',
        dialogList: [],
        socket: {},
        ctx: {},
        name: '',//登录的用户名
        extra: '',
        remain_time:'',
        current_color : '#339933',
        penWidth: 1,
        imageList : ['images/wz_2.jpg','images/wz_3.jpg','images/wz_1.jpg','images/wz_5.jpg'],
        showPicBox:0,
        score:[],
        seats : [{'name':'空位'},{'name':'空位'},{'name':'空位'},{'name':'空位'}],
        seatNum:0,
        gameStatus:'wait'

    },

    created: function () {
        let that = this;
        let canvas = document.getElementById("canvas");

        that.ctx = canvas.getContext("2d");

        let msg = "";

        let address = "ws://192.168.60.132:9501";
        this.socket = new WebSocket(address);
        that.socket.onerror = function (event) {
            console.log("服务器连接错误，请稍后重试");
        };

        this.socket.onclose = function (event) {
            console.log("散了吧，服务器都关了");
        };

        this.socket.onmessage = this.websocketonmessage;

    },
    watch: {
        current_color(val) {
            let that=this;
            let data = {};
            data.color = this.current_color;
            data.type = "change_color";
            that.socket.send(JSON.stringify(data));
        },
        penWidth(val) {
            let that=this;
            let data = {};
            data.width = that.penWidth;
            data.type = "pen_width";
            that.socket.send(JSON.stringify(data));
        },
    },
    mounted: function () {
        //this.login();
    },
    methods: {
        websocketonmessage(event) { //数据接收
            that = this;
            msg = event.data
            let data = JSON.parse(msg);

            if (data.type == "message" || data.type == 'image') {
                let ori_list = that.scoreList;
                let tmp = new Object();
                if(data.type == 'message'){
                    tmp.content = data.content;
                    tmp.content_type = 'text';
                } else {
                    tmp.url = data.url;
                }
                tmp.type = data.type;
                tmp.name = data.user;
                ori_list.push(tmp);
                //that.drawingUser = data.drawingUser;
                //that.scoreList = ori_list;
                that.extra = data.extra;
                that.score = data.score;
                //that.users = data.users;
                if (data.clearBoard == 1) {
                    this.clearBoard();
                }
                let ele = document.getElementById('app');
                this.$nextTick(() => {
                    document.documentElement.scrollTop =ele.scrollHeight;
                    that.showPicBox = 0;
                });

            } else if (data.type === 'line') {
                this.drawLine(data.startX, data.startY, data.endX, data.endY, 1);
            }  else if (data.type === 'timer') {
                that.remain_time = data.remain_time;
            }
            else if (data.type === 'users') {
                console.log(data)
                that.extra = data.extra;
                that.users = data.users;
                that.score = data.score;
                that.seats = data.seats;
                console.log(that.seats);
                //that.drawingUser = data.drawingUser;
            } else if (data.type === 'change_color') {
                that.current_color = data.color;
            }
            else if (data.type === 'pen_width') {
                that.penWidth = data.width;
            } else if (data.type == "startgame") {
                that.drawingUser = data.drawingUser;
                that.extra = data.extra;
                that.score = data.score;
                that.gameStatus = 'playing';
                //that.users = data.users;
                if (data.clearBoard == 1) {
                    this.clearBoard();
                }
            }else if (data.type == "restartgame") {
                that.drawingUser = data.drawingUser;
                that.extra = data.extra;
                that.score = data.score;
                that.gameStatus = 'restart';
                //that.users = data.users;
                if (data.clearBoard == 1) {
                    this.clearBoard();
                }
            } else if (data.type == "change_score") {
                let that=this;
                let ori_list = that.scoreList;
                //增加答对人的分数
                if(data.user){
                    that.users.forEach(function (v,k) {
                        if(v.name == data.user){
                            that.users[k].score = data.score;
                        }
                    })
                } else {
                    //重置所有分数为0
                    that.users.forEach(function (v,k) {
                        that.users[k].score = 0;
                    })
                }
                if (data.clearBoard == 1) {
                    this.clearBoard();
                }
                let tmp = new Object();
                tmp.content = data.content;
                that.extra = data.extra;
                that.drawingUser = data.drawingUser;
                tmp.content_type = data.content_type;
                tmp.name = data.content_user;
                ori_list.push(tmp);
                that.scoreList = ori_list;
            } else if (data.type == "enter_room") {
                that.seats = data.seats;
            }else if (data.type == "end_game") {
                that.gameStatus = 'restart';
                alert('游戏结束,胜利者：'+data.winner)
            }
        },
        login() {
            var name = prompt("请输入您的名字", ""); //将输入的内容赋给变量 name ，
            let that = this;
            if (name)//如果返回的有内容
            {
                this.name = name;
                let data = {};
                data.user = name;
                data.type = "bind";
                data.seat_num = that.seatNum;
                that.socket.send(JSON.stringify(data));
            }

        },
        sendmsg() {

            let that = this;
            let value = that.sendText;
            if (value !== "") {
                let data = {};
                data.type = "message";
                data.user = that.name;
                data.content = value;
                console.log(JSON.stringify(data))
                that.socket.send(JSON.stringify(data));
                that.sendText = '';
            }
        },
        startgame() {
            let that = this;
            let data = {};
            data.type = "startgame";
            data.user = this.name;
            that.socket.send(JSON.stringify(data));
        },
        restartgame() {
            let that = this;
            let data = {};
            data.type = "restartgame";
            data.user = this.name;
            that.socket.send(JSON.stringify(data));
        },
        drawline(event) {
            let that = this;
            if (gameObj.isDrawing && that.name == that.drawingUser) {
                mouseX = event.offsetX;
                mouseY = event.offsetY;
                //console.log(e.clientX,e.clientY)
                if (gameObj.startX !== mouseX && gameObj.startY !== mouseY) {
                    //console.log(gameObj.startX,gameObj.startY,mouseX,mouseY)
                    this.drawLine(/*that.ctx,*/gameObj.startX, gameObj.startY, mouseX, mouseY, 1)
                    let data = {};
                    data.startX = gameObj.startX;
                    data.startY = gameObj.startY;
                    data.endX = mouseX;
                    data.endY = mouseY;
                    data.type = 'line';
                    that.socket.send(JSON.stringify(data));

                    gameObj.startX = mouseX;
                    gameObj.startY = mouseY;
                }
            }

        },
        endDraw() {
            gameObj.isDrawing = false;
        },
        startDraw(event) {
            mouseX = event.offsetX,
            mouseY = event.offsetY;
            gameObj.startX = mouseX;
            gameObj.startY = mouseY;

            gameObj.isDrawing = true;
        },
        // // 画线函数
        drawLine(/*ctx, */x1, y1, x2, y2, thick) {
            let canvas = document.getElementById("canvas");
            ctx = canvas.getContext("2d");
            //console.log(ctx)
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.lineWidth = this.penWidth;
            ctx.strokeStyle = this.current_color;
            ctx.stroke();
        },
        clearBoard(){
            let canvas = document.getElementById("canvas");
            ctx = canvas.getContext("2d");
            canvas.height = canvas.height;
        },
        sendPic(url) {

                let data = {};
                data.type = "image";
                data.user = this.name;
                data.url = url;
                console.log(data)
                this.socket.send(JSON.stringify(data));
                this.showPicBox =0;
        },
        setSeat(num,item){
            if(item.name != '空位'){
                return false;
            }
            let that = this;
            console.log(parseInt(num)+1)
            that.seatNum = parseInt(num)+1;
            that.login()
        }


    }
})







