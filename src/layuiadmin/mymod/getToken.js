/**
 扩展一个获取token模块
 **/

layui.define(function(exports){
    var $ = layui.jquery;
    var obj = {
        getToken: function(){
            $.ajax({
                url:'/index/refreshToken',
                type:"get",
                async: false,
                dataType:"json",
                success : function(data){
                    token = data;
                }
            });

            return token;
        }
    };

    //输出test接口
    exports('getToken', obj);
});