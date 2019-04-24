<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/8/22
 * Time: 22:14
 */
namespace app\manage\model;
use think\Model;

class UserTemplate extends Model{

  public  function Qrcode($param) {
      //加载第三方类库
      vendor('phpqrcode.phpqrcode');
      $url=config("DistributeUrl").urlencode(compile($param));
      $size=4;    //图片大小
      $errorCorrectionLevel = "Q"; // 容错级别：L、M、Q、H
      $matrixPointSize = "8"; // 点的大小：1到10
      //实例化
      $qr = new \QRcode();
      //会清除缓冲区的内容，并将缓冲区关闭，但不会输出内容。
      ob_end_clean();
      $output = 'upload/Qrcode/'.$param.'.png';
      $qr::png($url, $output, $errorCorrectionLevel, $matrixPointSize);
  }


}