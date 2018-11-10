<?php
class fenciClass
{
	static function fenci($info,&$dict)
	{
		mb_internal_encoding("UTF-8");

		$startPos = 0; //当前匹配开始位置
		$endPos   = 0; //最后匹配的位置
		$preState = 0; //前一个字符的属性 
		$curState = 0; //当前处理字符的属性
		$word     = NULL;   //当前匹配到的词组信息
		$wordAttr = NULL;   //最后匹配的属性
		$dictPos  = &$dict; //匹配的词典位置
		$isDiv 	  = false;	//当前匹配的字符是否为"/"
		$isPoi	  = false;	//当前匹配的字符是否为"."
		$isPer	  = false;	//当前匹配的字符是否为"%"
		$record	  = NULL;   //记录"/",".","%"出现的位置

		$i = 0;
		$ret = array();
		$result = $info;
		$sLen = mb_strlen($info);
		do
		{
			$curChar  = mb_substr($info,$i,1);
			$curState = self::charStat($curChar);
			if( $curChar == '/' && $preState == 2 )
			{
				$record =  $i;
				$isDiv = true;						
			}	
			if( $curChar == '.' && $preState == 2 )
			{
				$record =  $i;
				$isPoi = true;
			}
			if( $curChar == '%' && $preState == 2 )
			{
				$record =  $i;
				$isPer = true;
			}
			if( 0 == $curState && isset($dict[$curChar]))
			{
				$curState = 3;
			}

			//如果使用下列函数调用，不知为什么，dictPos这个引用参数的修改值无法返回。只好把该函数直接写到本函数
			//$r = self::spliteWord($dictPos,$curChar,$preState,$curState);
			if(!isset($dictPos[$curChar]))
			{
				//数字和单词在字典中没有，要单独处理
				//状态:0:切换 1:英文 2:数字 3:中文 4:忽略不处理 
				if( 1 != $curState && 2!=$curState)
				{
					$r =  0;
				}
				else if( $preState!=$curState && 0 != $preState )
				{
					$r =  0;
				}
				else if( 1 == $curState )
				{
					$r =  array('zm');//字母
				}
				else if( 2 == $curState )
				{
					$r =  array('sz');//数字
				}
				else
				{
					$r = 0;
				}
			}
			else
			{
				$r = 1;
				if( isset($dictPos[$curChar]['attr']) )
				{
					$w = mb_substr($info,$startPos,($endPos-$startPos));
					if( is_numeric($w) ) $r = 0;
					else $r = $dictPos[$curChar]['attr'];
				}
				$dictPos = &$dictPos[$curChar];
			}

			switch($r)
			{
				case 0: //无法匹配
					if( $endPos != $startPos ) //有匹配到，保存分词信息
					{
						$word = mb_substr($info,$startPos,($endPos-$startPos));
						$ret = self::toDecimals($wordAttr,$startPos,$record,$ret,$isDiv,$isPoi,$isPer,$word);					
						$result = str_replace($word,'',$result);
						//回退到匹配的最后一个字重新开始
						$startPos = $endPos;
						$endPos   = $startPos;
						$i        = $startPos;
						$preState = 0;
					}
					else
					{
						$preState = $curState;
						if( 1 != $curState && 2!=$curState)
						{
							$i = $startPos+1;
						}
						$startPos = $i;
						$endPos   = $startPos;
					}
					
					//字典也恢复到最开始
					$dictPos  = &$dict;
					break;
				case 1: //可以匹配，但还没成词。继续取下一个字符处理
					$i++;
					$preState = $curState;
					break;
				default://可以匹配，且成词
					$endPos   = $i+1;
					$wordAttr = $r; //保存匹配到的属性
					$preState = $curState;
					$i++; //继续下一个
					break;
			}
		}while($i < $sLen);

		if( $endPos != $startPos ) //有匹配到，保存分词信息
		{
			$word = mb_substr($info,$startPos,($endPos-$startPos));
			$ret = self::toDecimals($wordAttr,$startPos,$record,$ret,$isDiv,$isPoi,$isPer,$word);
			$result = str_replace($word,'',$result);
		}

		if( strstr($result,'一百') != false || strstr($result,'百分百') != false || strstr($result,'百分之百') != false  )
		{
			$result = str_replace('一百','',$result);
			$result = str_replace('百分百','',$result);
			$result = str_replace('百分之百','',$result);
			$ret[] = array('word'=>100,'attr'=>array('sz'));		
		}
		$result = self::toNumber($result);
		if( !empty($result) )
		{
			$ret[] = array('word'=>$result,'attr'=>array('sz'));							
		}
		return $ret;
	}
	
	///////////////////////////////////////////////////////////////////////
	//将匹配的词或者数字记录，如果是分数或者小数，或者百分数，都转化成一个小数
	static function toDecimals($wordAttr,$startPos,$record,$ret,$isDiv,$isPoi,$isPer,$word)
	{
		if( $wordAttr == array('sz') && $record != NULL && $startPos-$record == 1)
		{
			if( array_slice($ret,-1)[0]['attr'] == array('sz') )
			{
				if( $isDiv )
				{
					$ret[count($ret)-1]['word'] /= $word;
				}
				elseif( $isPoi )
				{
					$ret[count($ret)-1]['word'] = $ret[count($ret)-1]['word'] + $word / ( $word > 10 ? 100 : 10 ); 
				}
			}
		}
		else
		{	
			if ( $wordAttr == array('sz') && $isPer ) {
				$word = $word/100;
			}
			$ret[] = array('word'=>$word,'attr'=>$wordAttr);				
		}		
		return $ret;
	}
	
	//大写数字转化为阿拉伯数字
	static function toNumber($value)
	{
		$len = mb_strlen($value);
		$number = array('零' => 0,'一' => 1,'二' => 2,'三' => 3,'四' => 4,'五' => 5,'六' => 6,'七' => 7,'八' => 8,'九' => 9,'十' => 10);
		$num = array();
		for( $i=0 ; $i < $len ; $i++)
		{
			$v = mb_substr($value,$i,1);
			if( isset($number[$v]) )
			{
				$num[] = $number[$v];		
			}
			else
			{
		 		if( sizeof($num) )
				{
					break;
				} 		
			}
		}
		$key = array_search(10,$num);
		if( is_int($key) && is_int($num[$key+1]) && isset($num[$key+1]) )
		{
			$num[$key] = intval(substr($num[$key],0,1));
		} 
		if( is_int($key) && is_int($num[$key-1]) && isset($num[$key-1]) )
		{
			if( mb_strlen($num[$key]) == 2 )
			{
				$num[$key] = intval(substr($num[$key],1,1));		
			}
			else
			{
				unset($num[$key]);
			}
		} 
		return implode($num);		
	}

	//返回值：0：匹配不到，1，匹配，2匹配且是个单词
	static function spliteWord(&$dictPos,$curChar,$preState,$curState)
	{
		if(!isset($dictPos[$curChar]))
		{
			//数字和单词在字典中没有，要单独处理
			//状态:0:切换 1:英文 2:数字 3:中文 4:忽略不处理
			if( 1 != $curState || 2!=$curState)
			{
				return 0;
			}
			if( $preState!=$curState && 0 != $preState )
			{
				return 0;
			}
			if( 1 == $curState )
			{
				return 'zm';//字母
			}
			if( 2 == $curState )
			{
				return 'sz';//数字
			}
			return 0;
		}
		
		$r = 1;
		if( isset($dictPos[$curChar]['attr']) )
		{
			$r = $dictPos[$curChar]['attr'];
		}
		$dictPos = &$dictPos[$curChar];
		return $r;
	}
	
	//状态:0:切换 1:英文 2:数字 3:中文 4:忽略不处理
	private static function charStat($curChar)
	{
		$x = ord( $curChar[0] );
		if( $x >= 128 )
		{
			return 3;
		}
		
		if ( 65<= $x && 90>=$x )//大写字母
		{
			return 1;
		}
		else if ( 97<= $x && 122>=$x )//小写字母
		{
			return  1;
		}
		else if ( 48 <= $x && 57 >= $x ) //判断是否数字
		{
			return 2;
		}
		else
		{
			return 0;
		}
		return 4;
	}
}

?>