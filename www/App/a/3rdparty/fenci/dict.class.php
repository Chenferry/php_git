<?php

class dictClass
{
	//构建词典
	//wordList->array('word'=>$info,'attr'=>$attr)
	//action:1 合并 2 删除
	static function buildDict(&$wordList,&$dict,$action=1)
	{
		//按树方式处理字典
		foreach($wordList as &$wordInfo)
		{
			$word = trim($wordInfo['word']);
			$attr = $wordInfo['attr'];

			mb_internal_encoding("UTF-8");
			$len = mb_strlen($word);
			$wArr = &$dict;
			for($i=0;$i<$len;$i++)
			{
				$curChar = mb_substr($word,$i,1);
				if ( !isset( $wArr[ $curChar ] ) )
				{
					$wArr[ $curChar ] = array();
				}
				if ( $i >= $len-1) //表示到此已经可以组成一个终结词了
				{
					if( !isset( $wArr[ $curChar ]['attr'] ) )
					{
						$wArr[ $curChar ]['attr'] = array();
					}
					$wArr[ $curChar ]['attr'][] = $attr; 
					$wArr[ $curChar ]['attr'] = array_unique( $wArr[ $curChar ]['attr'] );
				}
				$wArr = &$wArr[ $curChar ]; //继续，以树状组织词库
			}			
		}
		return;
	}
	
	//根据词典生成数组
	static function genWordList(&$dictArr, &$wordArr, $word=NULL)
	{
		foreach($dictArr as $key=>&$val)
		{
			$newword = $word.$key;
			if ( isset( $val[0] ))
			{
				$wordArr[] = $newword;
				unset($val[0]);
			} 
			
			if ( 0 == count($val) )
			{
				continue;
			}
			
			if ( !is_array($val) )
			{
				continue;
			}
			
			self::genWordList( $val, $wordArr,$newword );
		}
		return;	
	}
}

?>