<?php
/**
 * @合并js || css, 减少http请求
 * 格式: /??123,456.css
 * 说明: [?:缓存, ??:不缓存, 123,456:文件名, .css:后缀]
 * 处理: 合并, 缓存, 压缩
 * 作者: http://vtens.com
 * 时间: 2018-12-19
 */
$c = __DIR__ . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR; //缓存目录
$q = explode('.', strtolower(strip_tags($_SERVER['QUERY_STRING']))); //获取后缀数组

//初级处理
if(count($q) != 2) die('suffix error');
$cache = $q[0][0] !== '?' ? true : false;
$q[0] = $cache ? $q[0] : substr($q[0],1);

//后缀处理
switch($q[1] = '.' . $q[1])
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
if(is_file($file) && $cache)
{
    die(file_get_contents($file));
}else{
    //字符处理
    $q[0] = array_unique(explode(',', $q[0]));
    //合并代码
    $data = '';
    foreach($q[0] as $k => $v)
	{
        if(is_file($v . $q[1]))
		{
            $data.= file_get_contents($v . $q[1]) . PHP_EOL;
        }else{
            unset($q[0][$k]);
        }
    }
    if(!$data) die('no files'); //bug处理(都没有直接退出!)

    //压缩代码
    if($q[1] == '.css')
	{
        $data = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $data);
        echo $data = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $data);
    }
    if($q[1] == '.js')
	{
        // require('jsmin.php');
        echo $data = JSMin::minify($data);
    }

    //保存代码
    $file = $c . md5(implode(',', $q[0])) . $q[1];
    file_put_contents($file, $data);
}

/**
 * JSMIN 压缩类
 */
class JSMin {
    const ORD_LF = 10;
    const ORD_SPACE = 32;
    const ACTION_KEEP_A = 1;
    const ACTION_DELETE_A = 2;
    const ACTION_DELETE_A_B = 3;
    protected $a = '';
    protected $b = '';
    protected $input = '';
    protected $inputIndex = 0;
    protected $inputLength = 0;
    protected $lookAhead = null;
    protected $output = '';
    public static function minify($js) {
        $jsmin = new JSMin($js);
        return $jsmin->min();
    }
    public function __construct($input) {
        $this->input = str_replace("\r\n", "\n", $input);
        $this->inputLength = strlen($this->input);
    }
    protected function action($command) {
        switch ($command) {
            case self::ACTION_KEEP_A:
                $this->output.= $this->a;
            case self::ACTION_DELETE_A:
                $this->a = $this->b;
                if ($this->a === "'" || $this->a === '"') {
                    for (;;) {
                        $this->output.= $this->a;
                        $this->a = $this->get();
                        if ($this->a === $this->b) {
                            break;
                        }
                        if (ord($this->a) <= self::ORD_LF) {
                            throw new JSMinException('Unterminated string literal.');
                        }
                        if ($this->a === '\\') {
                            $this->output.= $this->a;
                            $this->a = $this->get();
                        }
                    }
                }
            case self::ACTION_DELETE_A_B:
                $this->b = $this->next();
                if ($this->b === '/' && ($this->a === '(' || $this->a === ',' || $this->a === '=' || $this->a === ':' || $this->a === '[' || $this->a === '!' || $this->a === '&' || $this->a === '|' || $this->a === '?' || $this->a === '{' || $this->a === '}' || $this->a === ';' || $this->a === "\n")) {
                    $this->output.= $this->a . $this->b;
                    for (;;) {
                        $this->a = $this->get();
                        if ($this->a === '[') {
                            /*
                            inside a regex [...] set, which MAY contain a '/' itself. Example: mootools Form.Validator near line 460:
                            return Form.Validator.getValidator('IsEmpty').test(element) || (/^(?:[a-z0-9!#$%&'*+/=?^_`{|}~-]\.?){0,63}[a-z0-9!#$%&'*+/=?^_`{|}~-]@(?:(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\])$/i).test(element.get('value'));
                            */
                            for (;;) {
                                $this->output.= $this->a;
                                $this->a = $this->get();
                                if ($this->a === ']') {
                                    break;
                                } elseif ($this->a === '\\') {
                                    $this->output.= $this->a;
                                    $this->a = $this->get();
                                } elseif (ord($this->a) <= self::ORD_LF) {
                                    throw new JSMinException('Unterminated regular expression set in regex literal.');
                                }
                            }
                        } elseif ($this->a === '/') {
                            break;
                        } elseif ($this->a === '\\') {
                            $this->output.= $this->a;
                            $this->a = $this->get();
                        } elseif (ord($this->a) <= self::ORD_LF) {
                            throw new JSMinException('Unterminated regular expression literal.');
                        }
                        $this->output.= $this->a;
                    }
                    $this->b = $this->next();
                }
        }
    }
    protected function get() {
        $c = $this->lookAhead;
        $this->lookAhead = null;
        if ($c === null) {
            if ($this->inputIndex < $this->inputLength) {
                $c = substr($this->input, $this->inputIndex, 1);
                $this->inputIndex+= 1;
            } else {
                $c = null;
            }
        }
        if ($c === "\r") {
            return "\n";
        }
        if ($c === null || $c === "\n" || ord($c) >= self::ORD_SPACE) {
            return $c;
        }
        return ' ';
    }
    protected function isAlphaNum($c) {
        return ord($c) > 126 || $c === '\\' || preg_match('/^[\w\$]$/', $c) === 1;
    }
    protected function min() {
        if (0 == strncmp($this->peek(), "\xef", 1)) {
            $this->get();
            $this->get();
            $this->get();
        }
        $this->a = "\n";
        $this->action(self::ACTION_DELETE_A_B);
        while ($this->a !== null) {
            switch ($this->a) {
                case ' ':
                    if ($this->isAlphaNum($this->b)) {
                        $this->action(self::ACTION_KEEP_A);
                    } else {
                        $this->action(self::ACTION_DELETE_A);
                    }
                break;
                case "\n":
                    switch ($this->b) {
                        case '{':
                        case '[':
                        case '(':
                        case '+':
                        case '-':
                        case '!':
                        case '~':
                            $this->action(self::ACTION_KEEP_A);
                        break;
                        case ' ':
                            $this->action(self::ACTION_DELETE_A_B);
                        break;
                        default:
                            if ($this->isAlphaNum($this->b)) {
                                $this->action(self::ACTION_KEEP_A);
                            } else {
                                $this->action(self::ACTION_DELETE_A);
                            }
                    }
                break;
                default:
                    switch ($this->b) {
                        case ' ':
                            if ($this->isAlphaNum($this->a)) {
                                $this->action(self::ACTION_KEEP_A);
                                break;
                            }
                            $this->action(self::ACTION_DELETE_A_B);
                        break;
                        case "\n":
                            switch ($this->a) {
                                case '}':
                                case ']':
                                case ')':
                                case '+':
                                case '-':
                                case '"':
                                case "'":
                                    $this->action(self::ACTION_KEEP_A);
                                break;
                                default:
                                    if ($this->isAlphaNum($this->a)) {
                                        $this->action(self::ACTION_KEEP_A);
                                    } else {
                                        $this->action(self::ACTION_DELETE_A_B);
                                    }
                            }
                        break;
                        default:
                            $this->action(self::ACTION_KEEP_A);
                        break;
                    }
            }
        }
        return $this->output;
    }
    protected function next() {
        $c = $this->get();
        if ($c === '/') {
            switch ($this->peek()) {
                case '/':
                    for (;;) {
                        $c = $this->get();
                        if (ord($c) <= self::ORD_LF) {
                            return $c;
                        }
                    }
                case '*':
                    $this->get();
                    for (;;) {
                        switch ($this->get()) {
                            case '*':
                                if ($this->peek() === '/') {
                                    $this->get();
                                    return ' ';
                                }
                            break;
                            case null:
                                throw new JSMinException('Unterminated comment.');
                        }
                    }
                default:
                    return $c;
            }
        }
        return $c;
    }
    protected function peek() {
        $this->lookAhead = $this->get();
        return $this->lookAhead;
    }
}
