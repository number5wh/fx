/**
 扩展一个发送短信请求
 **/

layui.define(function (exports) {
    var $ = layui.jquery;
    var obj = {
        sendmsg: function (id, type,mobile) {
            var InterValObj; //timer变量，控制时间
            var count = 120; //间隔函数，1秒执行
            var curCount;//当前剩余秒数
            var url;
            var data;
            if (type == 1) {
                url = '/sendmsg/index';
                data = {};
            } else {
                if(!(/^1\d{10}$/.test(mobile))){
                    layer.msg('手机号有误');
                    return false;
                }
                url = '/sendmsg/index2';
                data = {'mobile':mobile};
            }

            function sendMessage() {

                //请求后台发送验证码 TODO
                $.ajax({
                    type:'post',
                    url:url,
                    data:data,
                    dataType:'json',
                    success: function(res) {
                        if (res.code === 0) {
                            curCount = count;
                            //设置button效果，开始计时
                            $(id).attr("disabled", "true");
                            $(id).html(curCount + "秒后可重新发送");
                            $(id).removeClass('layui-btn-normal').addClass('layui-btn-disabled');
                            InterValObj = window.setInterval(SetRemainTime, 1000); //启动计时器，1秒执行一次

                            layer.msg(res.msg,{icon:6});
                        } else {
                            layer.msg(res.msg, {icon:5});
                        }
                    }
                });
            }

            //timer处理函数
            function SetRemainTime() {
                if (curCount == 0) {
                    window.clearInterval(InterValObj);//停止计时器
                    $(id).removeAttr("disabled");//启用按钮
                    $(id).removeClass('layui-btn-disabled').addClass('layui-btn-normal');
                    $(id).html("获取验证码");
                } else {
                    curCount--;
                    $(id).html(curCount + "秒后可重新发送");
                }
            }
            sendMessage();
        }
    };


    //输出test接口
    exports('sendmsg', obj);
});