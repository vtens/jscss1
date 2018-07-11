<?php
/**
* @合并js | css , 减少http请求数量
* 格式: /?123,456.css#123 
* 说明: [123,456:文件, .css:后缀, #123:防止浏览器缓存(可选)]
* 处理: 合并, 缓存, 压缩
* 作者: http://vtens.com
*/
$c = __DIR__ . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR; //缓存目录
$q = explode('.',strtolower(strip_tags($_SERVER['QUERY_STRING']))); //获取后缀数组
if(count($q) != 2)die('suffix error');

//后缀处理
switch($q[1] = '.'.$q[1])
{
	case '.js':
		$type = 'application/javascript';
	break;

	case '.css':
		$type = 'text/css';
	break;
	
	default:
	die('no suffix');
}
header("Content-Type:{$type};charset=utf-8");

//缓存处理
$file = $c . md5($q[0]) . $q[1];
if(is_file($file))
{
	echo file_get_contents($file);die;
}
else
{
	//字符处理
	$q[0] = array_unique(explode(',', $q[0]));

	//合并代码
	$data = '';
	foreach($q[0] as $k=>$v)
	{
		if(is_file($v.$q[1]))
		{
			$data .= file_get_contents($v.$q[1]) . PHP_EOL;
		}
		else{unset($q[0][$k]);}
	}	
	if(!$data)die('no files'); //bug处理(都没有直接退出!)

	//压缩代码
	if($q[1] == '.css')
	{
		$data = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $data);
		echo $data = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $data);	
	}
	if($q[1] == '.js')
	{
		require('jsmin.php');
		echo $data = JSMin::minify($data);
	}
	
	//保存代码
	$file = $c . md5(implode(',',$q[0])) . $q[1];
	file_put_contents($file, $data);
}