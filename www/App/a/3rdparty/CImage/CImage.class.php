<?php

/**
 * $image = new CImage();
 * $s = $data;
 * $percent = 0.2;
 *
 * print($image->ThumbFlow($s, $percent));
 */
class CImage
{
	/**
	 *缩略图类型统一为.jpeg格式
	 *$srcFlow   原图片的二进制流  
	 *$percent   缩略比 
	*/
	public static function ThumbFlow(&$srcFlow, $percent)
	{
		//将文件载入到资源变量im中
		if(!function_exists("imagecreatefromstring"))
		{
			return false;
		}
		$im = imagecreatefromstring($srcFlow);
		
		//计算缩略图的宽高，宽高同时缩放，缩小率均为$percent
		$srcW = imagesx($im);
		$srcH = imagesy($im);
		$ftoW = $srcW * $percent;
		$ftoH = $srcH * $percent;
		
		if(function_exists("imagecreatetruecolor"))//检查函数是否定义
		{
			$ni = imagecreatetruecolor($ftoW, $ftoH);//新建一个真彩色图像
			if($ni)
			{
				//重采样拷贝部分图像并调整大小 可以保持较好的清晰度
				imagecopyresampled($ni, $im, 0, 0, 0, 0, $ftoW, $ftoH,$srcW, $srcH);
			}
			else
			{
				//拷贝部分图像并调整大小
				$ni = imagecreate($ftoW, $ftoH);
				imagecopyresized($ni, $im, 0, 0, 0 , 0, $ftoW, $ftoH,$srcW, $srcH);
			}
		}
		else
		{
			$ni = imagecreate($ftoW, $ftoH);
			imagecopyresized($ni, $im, 0, 0, 0, 0, $ftoW, $ftoH, $srcW, $srcH);
		}
		
		//储存二进制流
		ob_start();
		imagejpeg($ni);                            
		$toFlow = ob_get_contents();
		ob_end_clean();
	
		imageDestroy($ni);
		ImageDestroy($im);
		
		//返回缩略图的二进制流
		return $toFlow;
	}
}

?>