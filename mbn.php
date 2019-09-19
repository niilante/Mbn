<?php

class MbnErr extends Exception
{
   /**
    * Common error message object
    * @export
    * @constructor
    * @param string $fn Function name
    * @param string $msg Message
    * @param mixed $val Incorrect value to message, default null
    */
   public function __construct($fn, $msg, $val = null)
   {
      $ret = 'Mbn' . $fn . ' error: ' . $msg;
      if (func_num_args() !== 2) {
         if (is_array($val)) {
            $val = '[' . implode(',', $val) . ']';
         }
         $ret .= ': ' . ((strlen($val) > 20) ? (substr($val, 0, 18) . '..') : $val);
      }
      parent::__construct($ret);
   }

}

class Mbn
{
   //version of Mbn library
   protected static $MbnV = '1.45';
   //default precision
   protected static $MbnP = 2;
   //default separator
   protected static $MbnS = '.';
   //default truncate
   protected static $MbnT = false;
   //default truncate
   protected static $MbnE = null;
   //default truncate
   protected static $MbnF = false;
   //default limit
   protected static $MbnL = 1000;
   private $d = [];
   private $s = 1;

   /**
    * fill options with default parameters and check
    * @param array $opt params by reference
    * @param int $MbnDP default precision
    * @param string $MbnDS default separator
    * @param boolean $MbnDT default truncate
    * @param boolean $MbnDE default evaluation
    * @param boolean $MbnDF default format
    * @param boolean $MbnDL default limit
    * @param string $fname name of function for exception
    * @return array checked ad filled class options
    * @throws MbnErr invalid options
    */
   private static function prepareOpt($opt, $MbnDP, $MbnDS, $MbnDT, $MbnDE, $MbnDF, $MbnDL, $fname)
   {
      $MbnP = $MbnDP;
      $MbnS = $MbnDS;
      $MbnT = $MbnDT;
      $MbnE = $MbnDE;
      $MbnF = $MbnDF;
      $MbnL = $MbnDL;
      if (array_key_exists('MbnP', $opt)) {
         $MbnP = $opt['MbnP'];
         if (!(is_int($MbnP) || is_float($MbnP)) || $MbnP < 0 || is_infinite($MbnP) || (float)(int)$MbnP !== (float)$MbnP) {
            throw new MbnErr($fname, 'invalid precision (non-negative int)', $MbnP);
         }
      }
      if (array_key_exists('MbnS', $opt)) {
         $MbnS = $opt['MbnS'];
         if ($MbnS !== '.' && $MbnS !== ',') {
            throw new MbnErr($fname, 'invalid separator (dot, comma)', $MbnS);
         }
      }
      if (array_key_exists('MbnT', $opt)) {
         $MbnT = $opt['MbnT'];
         if ($MbnT !== true && $MbnT !== false) {
            throw new MbnErr($fname, 'invalid truncate (bool)', $MbnT);
         }
      }
      if (array_key_exists('MbnE', $opt)) {
         $MbnE = $opt['MbnE'];
         if ($MbnE !== true && $MbnE !== false && $MbnE !== null) {
            throw new MbnErr($fname, 'invalid evaluation (bool, null)', $MbnE);
         }
      }
      if (array_key_exists('MbnF', $opt)) {
         $MbnF = $opt['MbnF'];
         if ($MbnF !== true && $MbnF !== false) {
            throw new MbnErr($fname, 'invalid format (bool)', $MbnF);
         }
      }
      if (array_key_exists('MbnL', $opt)) {
         $MbnL = $opt['MbnL'];
         if (!(is_int($MbnL) || is_float($MbnL)) || $MbnL < 0 || is_infinite($MbnP) || (float)(int)$MbnP !== (float)$MbnP) {
            throw new MbnErr($fname, 'invalid limit (positive int)', $MbnP);
         }
      }
      return ['MbnV' => static::$MbnV, 'MbnP' => $MbnP, 'MbnS' => $MbnS, 'MbnT' => $MbnT, 'MbnE' => $MbnE, 'MbnF' => $MbnF, 'MbnL' => $MbnL];
   }

   /**
    * Private function, carries digits bigger than 9, and removes leading zeros
    * @throws MbnErr exceeded MbnL digits limit
    */
   private function mbnCarry()
   {
      $ad = &$this->d;
      $adlm1 = count($ad) - 1;
      $i = $adlm1;
      while ($i >= 0) {
         $adi = $ad[$i];
         while ($adi < 0) {
            $adi += 10;
            $ad[$i - 1]--;
         }
         $adid = $adi % 10;
         $adic = ($adi - $adid) / 10;
         $ad[$i] = $adid;
         if ($adic !== 0) {
            if ($i !== 0) {
               $ad[--$i] += $adic;
            } else {
               array_unshift($ad, $adic);
               $adlm1++;
            }
         } else {
            $i--;
         }
      }
      while ($adlm1 > static::$MbnP && $ad[0] === 0) {
         array_shift($ad);
         $adlm1--;
      }
      while ($adlm1 < static::$MbnP) {
         array_unshift($ad, 0);
         $adlm1++;
      }
      if ($adlm1 === static::$MbnP) {
         for ($i = 0; $i <= $adlm1 && $ad[$i] === 0; $i++) {
         }
         $this->s *= ($i <= $adlm1) ? 1 : 0;
      } elseif ($adlm1 - static::$MbnP > static::$MbnL) {
         throw new MbnErr('.limit', 'exceeded ' . static::$MbnL . ' digits limit');
      }

   }

   /**
    * Private function, if m is true, sets value to b and return value, otherwise returns b
    * @param Mbn $b
    * @param boolean $m
    * @return Mbn
    */
   private function mbnSetReturn($b, $m)
   {
      if ($m === true) {
         $this->d = &$b->d;
         $this->s = $b->s;
         return $this;
      }
      return $b;
   }

   /**
    * Private function, removes last digit and rounds next-to-last depending on it
    */
   private function mbnRoundLast()
   {
      $ad = &$this->d;
      $adl = count($ad);
      if ($adl < 2) {
         array_unshift($ad, 0);
         $adl++;
      }
      if (array_pop($ad) >= 5) {
         $ad[$adl - 2]++;
      }
      $this->mbnCarry();
   }

   /**
    * Private function, sets value from string
    * @param string $ns String or formula
    * @param array|boolean $v Variables, default null
    * @throws MbnErr invalid format, calc error
    */
   private function fromString($ns, $v = null)
   {
      $np = [];
      preg_match('/^\s*(=)?[\s=]*([+\\-])?\s*((.*\S)?)/', $ns, $np);
      $n = $np[3];
      if ($np[2] === '-') {
         $this->s = -1;
      }
      $ln = strpos($n, '.');
      if ($ln === false) {
         $ln = strpos($n, ',');
      }
      $nl = strlen($n);
      $al = $nl;
      if ($ln === false) {
         $ln = $nl;
      } else {
         $al = $ln + 1;
      }
      $l = max($al + static::$MbnP, $nl);
      for ($i = 0; $i <= $l; $i++) {
         $c = ($i < $nl) ? (ord($n[$i]) - 48) : 0;
         if ($c >= 0 && $c <= 9) {
            if ($i <= $al + static::$MbnP) {
               $this->d[] = $c;
            }
         } elseif (($i !== $ln || $nl === 1) && ($c !== -16 || ($i + 1) >= $ln)) {
            if ($v !== false && (is_array($v) || $v === true || static::$MbnE === true || (static::$MbnE !== false && $np[1] === '='))) {
               $this->set(static::mbnCalc($ns, $v));
               return;
            }
            throw new MbnErr('', 'invalid format', $ns);
         }
      }
      $this->mbnRoundLast();
   }

   /**
    * Private function, sets value from number
    * @param int|float|double $nn
    * @throws MbnErr infinite value
    */
   private function mbnFromNumber($nn)
   {
      if (!is_finite($nn)) {
         throw new MbnErr('', 'invalid value', $nn);
      }
      if ($nn < 0) {
         $nn = -$nn;
         $this->s = -1;
      }
      $ni = (int)$nn;
      $nf = $nn - $ni;
      do {
         $c = $ni % 10;
         $ni -= $c;
         $ni /= 10;
         array_unshift($this->d, $c);
      } while ($ni > 0);
      for ($n = 0; $n <= static::$MbnP; $n++) {
         $nf *= 10;
         $nfi = (int)$nf;
         $c = ($nfi === 10) ? 9 : $nfi;
         $this->d[] = $c;
         $nf -= $c;
      }
      $this->mbnRoundLast();
   }

   /**
    * Private function, returns string value
    * @param int $p Target precision
    * @param string $s Separator
    * @param boolean $t Trim zeros
    * @param boolean $f Format thousands
    * @return string
    */
   private function mbnToString($p, $s, $t, $f)
   {
      $v = $this;
      $li = count($this->d) - static::$MbnP;
      if ($p < static::$MbnP) {
         $b = new static($this);
         $bl = count($b->d);
         if ($p < static::$MbnP - 1) {
            $b->d = array_slice($b->d, 0, $bl - static::$MbnP + $p + 1);
         }
         $b->mbnRoundLast();
         $bl = count($b->d);
         if ($bl - $p > $li) {
            $b->d = array_slice($b->d, $bl - $p - $li);
         }
         $v = $b;
      }
      $di = array_slice($v->d, 0, $li);
      if ($f === true) {
         $dl = count($di);
         for ($i = 0; 3 * $i < $dl - 3; $i++) {
            array_splice($di, -3 - 4 * $i, 0, ' ');
         }
      }
      $df = array_slice($v->d, $li);
      if ($p > static::$MbnP && !$t) {
         for ($i = 0; $i < $p - static::$MbnP; $i++) {
            $df[] = 0;
         }
      }
      if ($t) {
         for ($i = count($df) - 1; $i >= 0; $i--) {
            if ($df[$i] !== 0) {
               break;
            }
         }
         $df = array_slice($df, 0, $i + 1);
      }
      $r = (($this->s < 0) ? '-' : '') . implode('', $di);
      if (!empty($df)) {
         $r .= $s . implode('', $df);
      }
      return $r;
   }

   /**
    * Constructor of Mbn object
    * @export
    * @constructor
    * @param mixed $n Value, default 0
    * @param array|boolean $v Array with vars for evaluation, default null
    * @throws MbnErr invalid argument, invalid format, calc error
    */
   public function __construct($n = 0, $v = null)
   {
      if (is_float($n) || is_int($n)) {
         $this->mbnFromNumber($n);
      } elseif (is_object($n) || is_string($n)) {
         if ($n instanceof static) {
            $this->set($n);
            return;
         }
         $this->fromString($n, $v);
      } elseif (is_bool($n) || $n === null) {
         $this->mbnFromNumber((int)$n);
      } else {
         throw new MbnErr('', 'invalid argument', $n);
      }
   }

   /**
    * Returns properties of Mbn class
    * @return array properties
    * @throws MbnErr
    */
   public static function prop()
   {
      return static::prepareOpt(['MbnV' => static::$MbnV, 'MbnP' => static::$MbnP, 'MbnS' => static::$MbnS, 'MbnT' => static::$MbnT,
          'MbnE' => static::$MbnE, 'MbnF' => static::$MbnF, 'MbnL' => static::$MbnL], 0, 0, 0, 0, 0, 0, '.prop');
   }

   /**
    * Sets value from b
    * @param mixed $b
    * @return Mbn
    * @throws MbnErr invalid argument format
    */
   public function set($b)
   {
      if (!($b instanceof static)) {
         $this->mbnSetReturn(new static($b), true);
      } else {
         $this->d = $b->d;
         $this->s = $b->s;
      }
      return $this;
   }

   /**
    * Returns string value
    * @return string
    */
   public function toString()
   {
      return $this->mbnToString(static::$MbnP, static::$MbnS, static::$MbnT, static::$MbnF);
   }

   /**
    * Returns reformatted string value
    * @param bool|array $opt thousand grouping or object with params, default true
    * @return string
    */
   public function format($opt = true)
   {
      if (!is_array($opt)) {
         $opt = ['MbnF' => $opt === true];
      }
      $opt = static::prepareOpt($opt, static::$MbnP, static::$MbnS, static::$MbnT, static::$MbnE, static::$MbnF, static::$MbnL, '.format');
      return $this->mbnToString($opt['MbnP'], $opt['MbnS'], $opt['MbnT'], $opt['MbnF']);
   }

   /**
    * Returns string value
    * @return string
    */
   public function __toString()
   {
      return $this->toString();
   }

   /**
    * Returns int value for MbnP = 0, otherwise float (double) value
    * @return int|float|double
    */
   public function toNumber()
   {
      $v = $this->mbnToString(static::$MbnP, '.', false, false);
      return (static::$MbnP === 0) ? (int)$v : (float)$v;
   }

   /**
    * Compare value with b, a.cmp(b)<=0 means a<=b
    * @param mixed $b
    * @param mixed $d Maximum difference treated as equality, default 0
    * @return int 1 if value > b, -1 if value < b, otherwise 0
    * @throws MbnErr negative maximal difference
    * @throws MbnErr invalid argument format
    */
   public function cmp($b, $d = 0)
   {
      if ($d !== 0) {
         $dm = new static($d);
      }
      if (!($b instanceof static)) {
         $b = new static($b);
      }
      if ($d === 0 || $dm->s === 0) {
         if ($this->s !== $b->s) {
            return ($this->s > $b->s) ? 1 : -1;
         }
         if ($this->s === 0) {
            return 0;
         }
         $bl = count($b->d);
         $ld = count($this->d) - $bl;
         if ($ld !== 0) {
            return ($ld > 0) ? $this->s : -$this->s;
         }
         for ($i = 0; $i < $bl; $i++) {
            if ($this->d[$i] !== $b->d[$i]) {
               return ($this->d[$i] > $b->d[$i]) ? $this->s : -$this->s;
            }
         }
         return 0;
      }
      if ($dm->s === -1) {
         throw new MbnErr('.cmp', 'negative maximal difference', $dm);
      }
      if ($this->sub($b)->abs()->cmp($dm) <= 0) {
         return 0;
      }
      return $this->cmp($b);
   }

   /**
    * Add b to value
    * @param mixed $b
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    * @throws MbnErr invalid argument format
    */
   public function add($b, $m = false)
   {
      if (!($b instanceof static)) {
         $b = new static($b);
      }
      $r = new static($b);
      if ($this->s !== 0) {
         if ($b->s === 0) {
            $r->set($this);
         } else if ($b->s === $this->s) {
            $ld = count($this->d) - count($b->d);
            if ($ld < 0) {
               $b = $this;
               $ld = -$ld;
            } else {
               $r->set($this);
            }
            $rl = count($r->d);
            for ($i = 0; $i < $rl; $i++) {
               if ($i >= $ld) {
                  $r->d[$i] += $b->d[$i - $ld];
               }
            }
            $r->mbnCarry();
         } else {
            $r->s = -$r->s;
            $r->sub($this, true);
            $r->s = -$r->s;
         }
      }
      return $this->mbnSetReturn($r, $m);
   }

   /**
    * Subtract b from value
    * @param mixed $b
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    * @throws MbnErr invalid argument format
    */
   public function sub($b, $m = false)
   {
      if (!($b instanceof static)) {
         $b = new static($b);
      }
      $r = new static($b);
      if ($this->s === 0) {
         $r->s = -$r->s;
      } else if ($b->s === 0) {
         $r->set($this);
      } else if ($b->s === $this->s) {
         $ld = count($this->d) - count($b->d);
         $cmp = $this->cmp($b) * $this->s;
         if ($cmp === 0) {
            $r = new static(0);
         } else {
            if ($cmp === -1) {
               $b = $this;
               $ld = -$ld;
            } else {
               $r->set($this);
            }
            $rl = count($r->d);
            for ($i = 0; $i < $rl; $i++) {
               if ($i >= $ld) {
                  $r->d[$i] -= $b->d[$i - $ld];
               }
            }
            $r->s = $cmp * $this->s;
            $r->mbnCarry();
         }
      } else {
         $r->s = -$r->s;
         $r->add($this, true);
      }
      return $this->mbnSetReturn($r, $m);
   }

   /**
    * Multiple value by b
    * @param mixed $b
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    * @throws MbnErr invalid argument format
    */
   public function mul($b, $m = false)
   {
      if (!($b instanceof static)) {
         $b = new static($b);
      }
      $r = new static($b);
      $r->d = [];
      $tc = count($this->d);
      $bc = count($b->d);
      for ($i = 0; $i < $tc; $i++) {
         for ($j = 0; $j < $bc; $j++) {
            $ipj = $i + $j;
            $r->d[$ipj] = $this->d[$i] * $b->d[$j] + (isset($r->d[$ipj]) ? $r->d[$ipj] : 0);
         }
      }
      $r->s = $this->s * $b->s;
      $r->mbnCarry();
      if (static::$MbnP >= 1) {
         if (static::$MbnP > 1) {
            $r->d = array_slice($r->d, 0, 1 - static::$MbnP);
         }
         $r->mbnRoundLast();
      }
      return $this->mbnSetReturn($r, $m);
   }

   /**
    * Divide value by b
    * @param mixed $b
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    * @throws MbnErr division by zero
    * @throws MbnErr invalid argument format
    */
   public function div($b, $m = false)
   {
      if (!($b instanceof static)) {
         $b = new static($b);
      }
      if ($b->s === 0) {
         throw new MbnErr('.div', 'division by zero');
      }
      if ($this->s === 0) {
         return $this->mbnSetReturn(new static($this), $m);
      }
      $x = $this->d;
      $y = $b->d;
      $p = 0;
      $ra = [0];
      while ($y[0] === 0) {
         array_shift($y);
      }
      while ($x[0] === 0) {
         array_shift($x);
      }
      $mp = static::$MbnP + 1;
      while (count($y) < count($x)) {
         $y[] = 0;
         $mp++;
      }
      do {
         while ($x[($xl = count($x)) - 1] + $y[($yl = count($y)) - 1] === 0) {
            array_pop($x);
            array_pop($y);
         }
         $xge = $xl >= $yl;
         if ($xl === $yl) {
            for ($i = 0; $i < $xl; $i++) {
               if ($x[$i] !== $y[$i]) {
                  $xge = $x[$i] > $y[$i];
                  break;
               }
            }
         }
         if ($xge) {
            $ra[$p] = 1 + (isset($ra[$p]) ? $ra[$p] : 0);
            $ld = $xl - $yl;
            for ($i = $yl - 1; $i >= 0; $i--) {
               if ($x[$i + $ld] < $y[$i]) {
                  $x[$i + $ld - 1]--;
                  $x[$i + $ld] += 10 - $y[$i];
               } else {
                  $x[$i + $ld] -= $y[$i];
               }
            }
         } else {
            $x[] = 0;
            $p++;
            $ra[$p] = 0;
         }
         while (isset($x[0]) && $x[0] === 0) {
            array_shift($x);
         }
      } while (count($x) !== 0 && $p <= $mp);
      while ($p <= $mp) {
         $ra[++$p] = 0;
      }
      array_pop($ra);
      $r = new static($b);
      $r->s *= $this->s;
      $r->d = $ra;
      $r->mbnRoundLast();
      return $this->mbnSetReturn($r, $m);
   }

   /**
    * Modulo, remainder of division value by b, keep sign of value
    * @param mixed $b
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    * @throws MbnErr division by zero
    * @throws MbnErr invalid argument format
    */
   public function mod($b, $m = false)
   {
      $ba = ($b instanceof static) ? $b->abs() : (new static($b))->abs();
      $r = $this->sub($this->div($ba)->intp()->mul($ba));
      if (($r->s * $this->s) === -1) {
         $r = $ba->sub($r->abs());
         $r->s = $this->s;
      }
      return $this->mbnSetReturn($r, $m);
   }

   /**
    * Split value to array of values, with same ratios as in given array, or to given number of parts, default 2
    * @param array|mixed $ar Ratios array or number of parts, default 2
    * @return Mbn[]
    * @throws MbnErr negative ratio, non-positive or not integer number of parts
    * @throws MbnErr invalid argument format
    */
   public function split($ar = 2)
   {
      $arr = [];
      if (!is_array($ar)) {
         $mbn1 = new static(1);
         $asum = new static($ar);
         if ($asum->s < 0 || !$asum->isInt()) {
            throw new MbnErr('.split', 'only natural number of parts supported');
         }
         $n = (int)$asum->toNumber();
         for ($i = 0; $i < $n; $i++) {
            $arr[] = $mbn1;
         }
         $brr = $arr;
      } else {
         $mulp = (new static(10))->pow(static::$MbnP);
         $asum = new static(0);
         $n = count($ar);
         $sgns = [false, false, false];
         foreach ($ar as $k => &$v) {
            $ai = (new static($v))->mul($mulp);
            $ai->k = $k;
            $sgns[$ai->s + 1] = true;
            $asum->add($ai, true);
            $arr[$k] = $ai;
         }
         unset($v);
         $brr = $arr;
         if ($sgns[0] && $sgns[2]) {
            usort($arr, static function ($a, $b) use ($asum){
               return $asum->s * $a->cmp($b);
            });
         }
      }
      if ($n === 0) {
         throw new MbnErr('.split', 'cannot split to zero parts');
      }
      if ($asum->s === 0) {
         throw new MbnErr('.split', 'cannot split when sum of parts is zero');
      }
      $a = new static($this);
      foreach ($arr as $k => &$v) {
         $idx = isset($v->k) ? $v->k : $k;
         if ($v->s === 0) {
            $brr[$idx] = $v;
         } else {
            $b = $a->mul($v)->div($asum);
            $asum->sub($v, true);
            $a->sub($b, true);
            $brr[$idx] = $b;
         }
      }
      unset($v);
      return $brr;
   }

   /**
    * Returns if the number is integer
    * @return boolean
    */
   public function isInt()
   {
      $ct = count($this->d);
      for ($l = $ct - static::$MbnP; $l < $ct; $l++) {
         if ($this->d[$l] !== 0) {
            return false;
         }
      }
      return true;
   }

   /**
    * Returns greatest integer value not greater than number
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    */
   public function floor($m = false)
   {
      $r = ($m === true) ? $this : new static($this);
      if (static::$MbnP !== 0) {
         $ds = 0;
         $ct = count($r->d);
         for ($l = $ct - static::$MbnP; $l < $ct; $l++) {
            $ds += $r->d[$l];
            $r->d[$l] = 0;
         }
         if ($r->s === -1 && $ds > 0) {
            $r->d[$ct - static::$MbnP - 1]++;
         }
         $r->mbnCarry();
      }
      return $r;
   }

   /**
    * Rounds number to closest integer value (half-up)
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    */
   public function round($m = false)
   {
      $r = ($m === true) ? $this : new static($this);
      if (static::$MbnP !== 0) {
         $ct = count($r->d);
         $l = $ct - static::$MbnP;
         $r->d[$l - 1] += ($r->d[$l] >= 5) ? 1 : 0;
         while ($l < $ct) {
            $r->d[$l++] = 0;
         }
         $r->mbnCarry();
      }
      return $r;
   }

   /**
    * Returns absolute value
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    */
   public function abs($m = false)
   {
      $r = ($m === true) ? $this : new static($this);
      $r->s *= $r->s;
      return $r;
   }

   /**
    * Returns additive inverse of value
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    */
   public function inva($m = false)
   {
      $r = ($m === true) ? $this : new static($this);
      $r->s = -$r->s;
      return $r;
   }

   /**
    * Returns multiplicative inverse of value
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    * @throws MbnErr division by zero
    */
   public function invm($m = false)
   {
      $r = (new static(1))->div($this);
      return $this->mbnSetReturn($r, $m);
   }

   /**
    * Returns lowest integer value not lower than number
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    */
   public function ceil($m = false)
   {
      $r = ($m === true) ? $this : new static($this);
      return $r->inva(true)->floor(true)->inva(true);
   }

   /**
    * Returns integer part of number
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    */
   public function intp($m = false)
   {
      $r = ($m === true) ? $this : new static($this);
      return ($r->s >= 0) ? $r->floor(true) : $r->ceil(true);
   }

   /**
    * Returns if value equals b
    * @param mixed $b
    * @param mixed $d Maximum difference treated as equality, default 0
    * @return boolean
    * @throws MbnErr negative maximal difference
    * @throws MbnErr invalid argument format
    */
   public function eq($b, $d = 0)
   {
      return $this->cmp($b, $d) === 0;
   }

   /**
    * Returns minimum from value and b
    * @param mixed $b
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    * @throws MbnErr invalid argument format
    */
   public function min($b, $m = false)
   {
      return $this->mbnSetReturn(new static(($this->cmp($b) <= 0) ? $this : $b), $m);
   }

   /**
    * Returns maximum from value and b
    * @param mixed $b
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    * @throws MbnErr invalid argument format
    */
   public function max($b, $m = false)
   {
      return $this->mbnSetReturn(new static(($this->cmp($b) >= 0) ? $this : $b), $m);
   }

   /**
    * Returns square root of value
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    * @throws MbnErr square root of negative number
    */
   public function sqrt($m = false)
   {
      $t = $this->mul(100);
      $rb = new static($t);
      $r = new static($t);
      $mbn2 = new static(2);
      if ($r->s === -1) {
         throw new MbnErr('.sqrt', 'square root of negative number', $this);
      }
      if ($r->s === 1) {
         do {
            $rb->set($r);
            $r->add($t->div($r), true)->div($mbn2, true);
         } while (!$rb->eq($r));
      }
      $r->mbnRoundLast();
      return $this->mbnSetReturn($r, $m);
   }

   /**
    * Returns sign from value, 1 - positive, -1 - negative, otherwise 0
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    */
   public function sgn($m = false)
   {
      return $this->mbnSetReturn(new static($this->s), $m);
   }

   /**
    * Returns value to the power of b, b must be integer
    * @param mixed $b
    * @param boolean $m Modify original variable, default false
    * @return Mbn
    * @throws MbnErr not integer exponent
    * @throws MbnErr invalid argument format
    */
   public function pow($b, $m = false)
   {
      $n = new static($b);
      if (!$n->isInt()) {
         throw new MbnErr('.pow', 'only integer exponents supported', $n);
      }
      $ns = $n->s;
      $n->s *= $n->s;
      $ni = (int)$n->toNumber();
      $mbn1 = new static(1);
      $rx = new static($this);
      if ($ns === -1 && $this->abs()->cmp($mbn1) === -1) {
         $rx->invm(true);
         $ns = -$ns;
      }
      $dd = 0;
      $cdd = 0;
      $r = new static($mbn1);
      while (!$rx->isInt()) {
         $rx->d[] = 0;
         $rx->mbnCarry();
         $dd++;
      }
      while (true) {
         if ($ni % 2 === 1) {
            $r->mul($rx, true);
            $cdd += $dd;
         }
         $ni = (int)($ni / 2);
         if ($ni === 0) {
            break;
         }
         $rx->mul($rx, true);
         $dd *= 2;
      }
      if ($cdd >= 1) {
         if ($cdd > 1) {
            $r->d = array_slice($r->d, 0, 1 - $cdd);
         }
         $r->mbnRoundLast();
      }
      if ($ns === -1) {
         $r->invm(true);
      }
      return $this->mbnSetReturn($r, $m);
   }

   /**
    * Returns factorial, value must be non-negative integer
    * @param boolean= m Modify original variable, default false
    * @return Mbn
    * @throws {MbnErr} value is not non-negative integer
    */
   public function fact($m = false)
   {
      if (!$this->isInt() || $this->cmp(0) === -1) {
         throw new MbnErr('.fact', 'only non-negative integers supported', $this);
      }
      $n = $this->sub(1);
      $r = new static($this);
      while ($n->s === 1) {
         $r->mul($n, true);
         $n->sub(1, true);
      }
      return $this->mbnSetReturn($r, $m);
   }

   protected static $fnReduce = ['set' => 0, 'abs' => 1, 'inva' => 1, 'invm' => 1, 'ceil' => 1, 'floor' => 1,
       'sqrt' => 1, 'round' => 1, 'sgn' => 1, 'intp' => 1,
       'min' => 2, 'max' => 2, 'add' => 2, 'sub' => 2, 'mul' => 2, 'div' => 2, 'mod' => 2, 'pow' => 2];

   /**
    * Runs function on each element, returns:
    * single value for 2 argument function (arr[0].fn(arr[1]).fn(arr[2]), ..)
    * array of products for 1 argument function [arr[0].fn(), arr[1].fn(), ..]
    * array of products for 2 argument function and when b is same size array or single value
    * [arr[0].fn(b[0]), arr[1].fn(b[1]), ..] or [arr[0].fn(b), arr[1].fn(b), ..]
    * @param string $fn
    * @param array|mixed $arr first argument
    * @param array|mixed $b second argument, defauult null
    * @return Mbn|Mbn[]
    * @throws MbnErr invalid function name, wrong number of arguments, different array sizes
    * @throws MbnErr invalid argument format
    */
   public static function reduce($fn, $arr, $b = null)
   {
      $inv = false;
      if (!is_string($fn) || !isset(static::$fnReduce[$fn])) {
         throw new MbnErr('.reduce', 'invalid function name', $fn);
      }
      if (!is_array($arr)) {
         if (!is_array($b)) {
            throw new MbnErr('.reduce', 'argument is not array', $arr);
         }
         $inv = $b;
         $b = $arr;
         $arr = $inv;
      }
      $mode = static::$fnReduce[$fn];
      $bmode = (func_num_args() === 3) ? (is_array($b) ? 2 : 1) : 0;
      if ($mode !== 2 && $bmode !== 0) {
         throw new MbnErr('.reduce', 'two arguments can be used with two-argument functions');
      }
      if ($mode === 2 && $bmode === 0) {
         $r = new static(0);
         $fst = true;
         foreach ($arr as $k => &$v) {
            if ($fst) {
               $r->set($v);
               $fst = false;
            } else {
               $r->{$fn}($v, true);
            }
         }
         unset($v);
      } else {
         $r = [];
         if ($bmode === 2 && array_keys($arr) !== array_keys($b)) {
            throw new MbnErr('.reduce', 'arrays have different length', $b);
         }
         $bv = ($bmode === 1) ? (new static($b)) : null;
         foreach ($arr as $k => &$v) {
            $e = new static($v);
            if ($bmode !== 0) {
               $bi = ($bmode === 2) ? (new static($b[$k])) : $bv;
               $e->set(($inv === false) ? $e->{$fn}($bi) : $bi->{$fn}($e));
            }
            $r[$k] = ($mode === 1) ? $e->{$fn}(true) : $e;
         }
         unset($v);
      }
      return $r;
   }

   protected static $MbnConst = [
       '' => ['PI' => '3.1415926535897932384626433832795028841972',
           'E' => '2.7182818284590452353602874713526624977573',
           'eps' => true]
   ];

   /**
    * Sets and reads constant
    * @param string|null $n Constant name, must start with letter or _
    * @param mixed v$ Constant value to set
    * @return Mbn|boolean
    * @throws MbnErr undefined constant, constant already set, incorrect name
    * @throws MbnErr invalid argument format
    */
   public static function def($n, $v = null)
   {
      $mc = &static::$MbnConst;
      $mx = get_class(new static());
      if ($n === null) {
         return (isset($mc[$mx][$v]) || isset($mc[''][$v]));
      }
      if ($v === null) {
         if (!isset($mc[$mx])) {
            $mc[$mx] = [];
         }
         if (!isset($mc[$mx][$n])) {
            if (!isset($mc[''][$n])) {
               throw new MbnErr('.def', 'undefined constant', $n);
            }
            $mc[$mx][$n] = ($n === 'eps') ? ((new static(10))->pow(-static::$MbnP)) : (new static($mc[''][$n]));
         }
         return new static($mc[$mx][$n]);
      }
      if (isset($mc[$mx][$n]) || isset($mc[''][$n])) {
         throw new MbnErr('.def', 'constant already set', $n);
      }
      if (preg_match('/^[A-Za-z_]\\w*/', $n) !== 1) {
         throw new MbnErr('.def', 'incorrect name', $n);
      }
      $v = new static($v);
      $mc[$mx][$n] = $v;
      return new static($v);
   }

   protected static $fnEval = [
       'abs' => true, 'inva' => false, 'ceil' => true, 'floor' => true, 'fact' => true,
       'sqrt' => true, 'round' => true, 'sgn' => true, 'int' => 'intp', 'div_100' => 'div_100'];
   protected static $states = [
       'endBop' => ['bop', 'pc', 'fs'],
       'uopVal' => ['num', 'name', 'uop', 'po'],
       'po' => ['po']
   ];
   protected static $ops = [
       '|' => [1, true, 'max'],
       '&' => [2, true, 'min'],
       '+' => [3, true, 'add'],
       '-' => [3, true, 'sub'],
       '*' => [4, true, 'mul'],
       '#' => [4, true, 'mod'],
       '/' => [4, true, 'div'],
       '^' => [5, false, 'pow'],
       '%' => [7, true, 'div_100'],
       '!' => [7, true, 'fact'],
       'inva' => [6, true, 'inva'],
       'fn' => [7, true]
   ];
   protected static $rxs = [
       'num' => ['rx' => '/^([0-9\., ]+)\\s*/', 'next' => 'endBop'],
       'name' => ['rx' => '/^([A-Za-z_]\\w*)\\s*/'],
       'fn' => ['next' => 'po'],
       'vr' => ['next' => 'endBop'],
       'bop' => ['rx' => '/^([-+\\*\\/#^&|])\\s*/', 'next' => 'uopVal'],
       'uop' => ['rx' => '/^([-+])\s*/', 'next' => 'uopVal'],
       'po' => ['rx' => '/^(\\()\\s*/', 'next' => 'uopVal'],
       'pc' => ['rx' => '/^(\\))\\s*/', 'next' => 'endBop'],
       'fs' => ['rx' => '/^([%!])\\s*/', 'next' => 'endBop']
   ];

   /**
    * Evaluate expression
    * @param string $exp Expression
    * @param array|boolean $vars Array with vars for evaluation, default null
    * @return Mbn
    * @throws MbnErr syntax error, operation error
    * @throws MbnErr invalid argument format
    */
   public static function calc($exp, $vars = null)
   {
      return new static($exp, is_array($vars) ? $vars : true);
   }

   /**
    * Check expression, get names of used vars
    * @param string $exp Expression
    * @return array|boolean
    */
   public static function check($exp)
   {
      try {
         return array_keys(static::mbnCalc($exp, false));
      } catch (MbnErr $e) {
         return false;
      }
   }

   /**
    * Evaluate expression
    * @param string $exp Expression
    * @param array|boolean $vars Array with vars for evaluation
    * @return Mbn|string[]
    * @throws MbnErr syntax error, operation error
    * @throws MbnErr invalid argument format
    */
   private static function mbnCalc($exp, $vars)
   {
      $onlyCheck = $vars === false;
      $expr = preg_replace('/^[\\s=]+/', '', $exp);
      if (!is_array($vars)) {
         $vars = [];
      }
      $varsUsed = [];
      $state = 'uopVal';
      $rpns = [];
      $rpno = [];
      $t = null;

      while ($expr !== '') {
         $mtch = [];
         foreach (static::$states[$state] as $t) {
            if (preg_match(static::$rxs[$t]['rx'], $expr, $mtch) === 1) {
               break;
            }
         }
         if (empty($mtch)) {
            if ($state === 'endBop') {
               $tok = '*';
               $t = 'bop';
            } else {
               throw new MbnErr('.calc', 'unexpected', $expr);
            }
         } else {
            $tok = $mtch[1];
            $expr = substr($expr, strlen($mtch[0]));
         }
         switch ($t) {
            case 'num':
               $rpns[] = new static($tok, false);
               break;
            case 'name':
               $t = 'vr';
               if (isset(static::$fnEval[$tok]) && static::$fnEval[$tok] !== false) {
                  $t = 'fn';
                  $rpno [] = array_merge(static::$ops['fn'], [$tok]);
               } elseif ($onlyCheck) {
                  if (empty($varsUsed[$tok])) {
                     $varsUsed[$tok] = true;
                  }
               } elseif (array_key_exists($tok, $vars)) {
                  if (!isset($varsUsed[$tok])) {
                     $varsUsed[$tok] = new static($vars[$tok]);
                  }
                  $rpns [] = new static($varsUsed[$tok]);
               } elseif (static::def(null, $tok)) {
                  $rpns [] = static::def($tok);
               } else {
                  throw new MbnErr('.calc', 'undefined', $tok);
               }
               break;
            case 'bop':
               $bop = static::$ops[$tok];
               while (($rolp = array_pop($rpno)) !== null) {
                  if ($rolp === '(' || ($rolp[0] <= $bop[0] - ($bop[1] ? 1 : 0))) {
                     $rpno[] = $rolp;
                     break;
                  }
                  $rpns[] = $rolp[2];
               }
               $rpno[] = $bop;
               break;
            case 'uop':
               if ($tok === '-') {
                  $rpno [] = static::$ops['inva'];
               }
               break;
            case 'po':
               $rpno [] = $tok;
               break;
            case 'pc':
               while (($rolp = array_pop($rpno)) !== '(') {
                  if ($rolp === null) {
                     throw new MbnErr('.calc', 'unexpected', ')');
                  }
                  $rpns[] = $rolp[2];
               }
               break;
            case 'fs':
               $op = static::$ops[$tok];
               while (($rolp = array_pop($rpno)) !== null) {
                  if ($rolp === '(' || ($rolp[0] <= $op[0] - ($op[1] ? 1 : 0))) {
                     $rpno[] = $rolp;
                     break;
                  }
                  $rpns[] = $rolp[2];
               }
               $rpno[] = $op;
               break;
            default:
         }
         $state = static::$rxs[$t]['next'];
      }
      while (($rolp = array_pop($rpno)) !== null) {
         if ($rolp === '(') {
            throw new MbnErr('.calc', 'unexpected', '(');
         }
         $rpns[] = $rolp[2];
      }
      if ($state !== 'endBop') {
         throw new MbnErr('.calc', 'unexpected', 'END');
      }

      if ($onlyCheck) {
         return $varsUsed;
      }

      $rpn = [];

      foreach ($rpns as &$tn) {
         if ($tn instanceof static) {
            $rpn[] = &$tn;
         } elseif (isset(static::$fnEval[$tn])) {
            if (is_string(static::$fnEval[$tn])) {
               $tn = static::$fnEval[$tn];
               if (strpos($tn, '_') !== false) {
                  $tn = explode('_', $tn);
                  $rpn[count($rpn) - 1]->{$tn[0]}($tn[1], true);
                  continue;
               }
            }
            $rpn[count($rpn) - 1]->{$tn}(true);
         } else {
            $rpn[count($rpn) - 2]->{$tn}(array_pop($rpn), true);
         }
      }
      return $rpn[0];
   }

}
