<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/9/21
 * Time: 15:33
 */
namespace app\command;


use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\Request;

//银行卡提现操作
class WithdrawBank extends  Command{

    protected function configure(){
        $this->setName("withdrawbank")
            ->setDefinition([                           //Option 和 Argument都可以有多个, Argument的读取和定义的顺序有关.请注意
                new Option('option', 'o', Option::VALUE_OPTIONAL, "命令option选项"),
                //使用方式  php think hello  --option test 或 -o test
                new Argument('test',Argument::OPTIONAL,"test参数"),
                //使用方式    php think hello  test1 (将输入的第一个参数test1赋值给定义的第一个Argument参数test)
                //...
            ])
            ->setDescription('获取第三方数据，存入数据库');
    }


    protected function execute(Input $input, Output $output){

        $request = Request::instance([                          //如果在希望代码中像浏览器一样使用input()等函数你需要示例化一个Request并手动赋值
            'get'=>$input->getArguments(),                    //示例1: 将input->Arguments赋值给Request->get  在代码中可以直接使用input('get.')获取参数
            'route'=>$input->getOptions()                       //示例2: 将input->Options赋值给Request->route   在代码中可以直接使用request()->route(false)获取
        ]);
        $request->module("manage");

        $output->writeln(controller('manage/Company')->withdrawbank());


    }

}

?>