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


$("#start").click(startGame);
$("#input").keyup(e = > {
    let keyCode = e.keyCode;
if (keyCode === 13) {
    sendMsg();
}
})


// 重玩
$("#restart").on("click", function () {
    let data = {};
    data.state = gameObj.RESTART;
    data.type = GAME;
    that.socket.send(JSON.stringify(data));

    $(this).hide();
});

// $("#refresh_answer").on("click", function() {


//     //$(this).hide();
// });

function startGame() {
    // let data={};
    // data.state = gameObj.START;
    //    data.type = GAME;
    //    console.log(data)
    //    socket.send(JSON.stringify(data));
    //    if(data.error !== undefined){

    //    } else {
    //    	$(this).hide();
    //    }
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
        scoreList: [{"name": "xj", "content": 10}, {"name": "xj1", "content": 20}, {"name": "xj2", "content": 30}],
        users: [{"name": "xj"}],
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
        extra: ''

    },

    created: function () {
        let that = this;
        let canvas = document.getElementById("canvas");

        that.ctx = canvas.getContext("2d");

        //this.drawLine(that.ctx,50,50,100,100,1)

        let msg = "";

        let address = "ws://192.168.60.132:9501";
        this.socket = new WebSocket(address);
        that.socket.onerror = function (event) {
            console.log("服务器连接错误，请稍后重试");
        };


        // socket.onopen = function (event) {
        // 	console.log("连接成功")
        // 	console.log(event)
        //     // if(!sessionStorage.getItem("username")) {
        //     //     setName();
        //     // }else {
        //     //     username = sessionStorage.getItem("username")
        //     //     webSocket.send(JSON.stringify({
        //     //         "message": username,
        //     //         "type": "init"
        //     //     }));
        //     // }
        // };
        //    // console.log(event);

        //};
        this.socket.onclose = function (event) {
            console.log("散了吧，服务器都关了");
        };

        this.socket.onmessage = this.websocketonmessage;

    },
    mounted: function () {
        //this.login();
    },
    methods: {
        toArray(original) {
            return JSON.parse(JSON.stringify(original));
        },
        websocketonmessage(event) { //数据接收
            that = this;
            msg = event.data
            let data = JSON.parse(msg);
            console.log(data)
            if (data.type == "message") {
                let ori_list = that.scoreList;
                //console.log(ori_list)
                let tmp = new Object();
                tmp.content = data.content;
                tmp.name = data.user;
                //console.log(JSON.stringify(tmp))
                ori_list.push(tmp);
                //console.log(list)
                that.drawingUser = data.drawingUser;
                that.scoreList = ori_list;
                that.extra = data.extra;
                if (data.clearBoard == 1) {
                    this.clearBoard();
                }

            } else if (data.type === 'line') {
                this.drawLine(data.startX, data.startY, data.endX, data.endY, 1);
            } else if (data.type === 'users') {
                console.log(data);
                that.extra = data.extra;
                that.users = data.users;
                that.drawingUser = data.drawingUser;
            }

            /*else if(data.type==GAME){
             if(data.state===gameObj.START){
             ctx.clearRect(0,0,cvs.width,cvs.height);
             $("#restart").hide();
             $("#start").hide();
             $("#history").html("");

             // 判断是不是画画的还是猜图的
             if (data.isPlayer) {
             gameObj.isPlayer = true;
             $("#history").append(`<li>轮到你了，请画出<span class="answer">${data.answer}</span>吧</li>`);
             } else {
             $("#history").append(`<li>游戏即将开始，请准备，有一分钟时间猜答案哦~</li>`);
             }
             }

             if(data.state==gameObj.OVER){
             gameObj.isPlayer=false;
             $("#restart").hide();
             $("#start").show();
             $("#history").append(`<li>本轮游戏的获胜者是： <span class="winner">${data.winner}</span>，正确答案是： ${data.answer}</li>`);
             }
             } */
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
                console.log(data)
                that.socket.send(JSON.stringify(data));
            }

        }, refresh_answer() {
            let data = {};
            data.state = 0;
            data.type = GETANSWER;
            that.socket.send(JSON.stringify(data));
        }, sendmsg() {

            let that = this;
            let value = that.sendText;
            if (value !== "") {
                let data = {};
                data.type = "message";
                data.user = that.name;
                data.content = value;
                console.log(JSON.stringify(data))
                that.socket.send(JSON.stringify(data));
                $("#input").val("");
            }
        },
        drawline(event) {
            let that = this;
            if (gameObj.isDrawing && that.name == that.drawingUser) {
                mouseX = event.offsetX,
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
            ctx.lineWidth = 3;
            ctx.strokeStyle = "#222";
            ctx.stroke();
        },
        clearBoard(){
            let canvas = document.getElementById("canvas");
            ctx = canvas.getContext("2d");
            canvas.height = canvas.height;
        }


    }
})







