<?php

class dictClass
{
	//�����ʵ�
	//wordList->array('word'=>$info,'attr'=>$attr)
	//action:1 �ϲ� 2 ɾ��
	static function buildDict(&$wordList,&$dict,$action=1)
	{
		//������ʽ�����ֵ�
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
				if ( $i >= $len-1) //��ʾ�����Ѿ��������һ���ս����
				{
					if( !isset( $wArr[ $curChar ]['attr'] ) )
					{
						$wArr[ $curChar ]['attr'] = array();
					}
					$wArr[ $curChar ]['attr'][] = $attr; 
					$wArr[ $curChar ]['attr'] = array_unique( $wArr[ $curChar ]['attr'] );
				}
				$wArr = &$wArr[ $curChar ]; //����������״��֯�ʿ�
			}			
		}
		return;
	}
	
	//���ݴʵ���������
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