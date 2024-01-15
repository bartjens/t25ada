<?php
class t25Parser {


	private $lijstGebruiksEenheid;
	private $lijstTijdsEenheid;
	private $lijstBCodes;

	public function __construct() {
		$this->loadTables();
		// print_r($this->lijstBCodes);
	}


	function splitGebruiksCode($codeLine) {
			$text='';
			$vrijetext='';

			/*


	7. Daarna volgt een spatie die de scheiding vormt naar de b-componenten
	8. Lees nu de b-componenten, gescheiden door spaties.
	*/

		$codes = explode(' ',$codeLine);

		$i=0;
		$subLijst=array();
		$lijst=array();
		foreach ($codes as $code) {
			if ($code=='')
				continue;
			//controleer of het een b-code is.
			if (isset($this->lijstBCodes->$code)) {
				$i++;
				$subLijst[$i][]=$code;
			} else {
				$subLijst[$i][]=$code;
			}
			$i++;
		}
		// echo 'sublijst';
		// print_r($subLijst);

		foreach ($subLijst as $arr) {
			if (is_array($arr)) {
				$lijst[]=implode(' ',$arr);
			}
		}
		$codes = $lijst;
		// print_r($codes);
		$splitCode=array();
		$component = 0;
		$stap=1;
		$teken=1;
		$componentLetter = array(1=>'X',2=>'t',3=>'Y',4=>'a',5=>'b');
		$leegComponent = array('X'=>'','t'=>'','Y'=>'','a'=>'','b'=>'');

		foreach ($codes as $code) {
			$stap=1;
			$teken=1;
			$component++;
			$splitCode[$component]=$leegComponent;
			if ($code=='')
				continue;
			if (!empty ($vrijetext)) {
				$vrijetext .= (' ' . $code);
				continue;
			}
			$code = str_replace(',','.',$code);
			//controleer of het een b-code is.
			if (isset($this->lijstBCodes->$code)) {
				$splitCode[$component]['b']=$code;

				continue;
			}
			// echo "\n<br/>" . $code;
			for ($i=0;$i<strlen($code);$i++) {
				$char = $code[$i];
				if ($char==';') {
					$vrijetext .= substr($code,$i,strlen($code));
					break;
				}

				switch ($stap) {
				case 1:
					/*
					1.	Lees eerste teken:
						Indien streepje: component 1 is leeg
						Indien spatie: alle 4 componenten leeg  ga naar stap 9
					2. 	Lees door totdat het volgende teken geen cijfer of punt of streepje meer is; Dan heb jecomponent 1 (X) gehad
					*/
					if ($teken==1) {

						if ($char==' ') {
							//Eerste letter is een spatie, dus doorlezen tot er een volgend component is gevonden
							$teken=0;
							continue 2;
						} elseif ($char=='-') {
							$splitCode[$component][$componentLetter[$stap]] = ($splitCode[$component][$componentLetter[$stap]]??'') . $char;
						} elseif (preg_match('/[0-9]|[\.]|[\-]|[\/]/',$char,$match)) {


							$splitCode[$component][$componentLetter[$stap]] = ($splitCode[$component][$componentLetter[$stap]]??'') . $char;
						} else {
							//Code afsluiten
							// Eerste letter is niet een streepje maar een letter, dus een B-code
							$stap=5;
							$splitCode[$component][$componentLetter[$stap]] = ($splitCode[$component][$componentLetter[$stap]]??'') . $char;
						}
					} else {
						// We zitten in de tweede letter
						if (preg_match('/[0-9]|[\.]|[\-]|[\/]/',$char,$match)) {
							$splitCode[$component][$componentLetter[$stap]] = ($splitCode[$component][$componentLetter[$stap]]??'') . $char;
						} else {
							// Het is geen match, dus een letter of spatie
							$teken=0;
							$stap=2;
							$i--;
						}
					}
					break;
				case 2:
					//3. Vervolgens alle letters op de volgende posities lezen: stop zodra het volgende teken geen letter is. Dit is component 2 (t).
					if (preg_match('/[A-Z]/',$char,$match)) {
						$splitCode[$component][$componentLetter[$stap]] = ($splitCode[$component][$componentLetter[$stap]]??'') . $char;
					} else {
						$teken=0;
						$stap=3;
						$i--;
					}
					break;
				case 3:
					/*
					4. Als het nu volgende teken een spatie is: component 3 (Y) is leeg
					5. Indien geen spatie: doorlezen zolang het een cijfer, punt of streepje is.
					*/
					if ($teken==1) {
						if ($char==' ') {
							$teken=0;
							$stap=4;
						} else {
							$splitCode[$component][$componentLetter[$stap]] = ($splitCode[$component][$componentLetter[$stap]]??'') . $char;
						}
					} else {
						if (preg_match('/[0-9]|[\.]|[\-]|[\/]/',$char,$match)) {
							$splitCode[$component][$componentLetter[$stap]] = ($splitCode[$component][$componentLetter[$stap]]??'') . $char;
						} else {
							$teken=0;
							$stap=4;
							$i--;
						}
					}
					break;
				case 4:
					// 6. Tenslotte weer alle letters lezen voor component 4 (a).
					if (preg_match('/[A-Z]/',$char,$match)) {
						$splitCode[$component][$componentLetter[$stap]] = ($splitCode[$component][$componentLetter[$stap]]??'') . $char;
					} else {
						$teken=0;
						$stap=1;
						$component++;
						$i--;
					}
					break;
				case 5:
					// B-code
					if ($char!==' ') {
						$splitCode[$component][$componentLetter[$stap]] = ($splitCode[$component][$componentLetter[$stap]]??'') . $char;
					} else {
						$stap=1;

						$teken=0;
						$i--;
					}
					break;
				}
				$teken++;
			}
		}
		//print_r($splitCode);
		//Parse Text;
		$compNum=0;
		$textArr=array();
		$set=0;
		$componenten = array();
		$freq=array();
		$aantal = array();
		foreach ($splitCode as $component) {
			$mv=0;

			$omreken[$compNum]=1;
			if (!empty($component['X'])) {
				$textArr[]=$component['X'];
				$freq[$compNum] = ($freq[$compNum] ?? 0) + $this->berekenAantal($component['X']);
				$set++;
				$componenten[$set]['X']=$component['X'];
			}
			if (!empty($component['t'])) {
                $tComp = $component['t'];
				$tmpLijstTijdsEenheid = null;
				if (!empty($this->lijstTijdsEenheid->$tComp)) {
					$tmpLijstTijdsEenheid = (array) $this->lijstTijdsEenheid->$tComp;
					$textArr[]='maal ' . $this->lijstTijdsEenheid->$tComp->teOms;
					$omreken[$compNum] = $this->lijstTijdsEenheid->$tComp->teDagOmreken;
				}
				if (!$omreken[$compNum])
					$omreken[$compNum]=1;
				if ($componenten[$set]['t'] ?? 0) {
					$set++;
				}
				// $componenten[$set]['t']=$component['t'];
				$componenten[$set]['t']=$tmpLijstTijdsEenheid;
			}

			if (!empty($component['Y'])) {
				$textArr[]=$component['Y'];
				if ($component['Y']>1 || strlen($component['Y'])>1) {
					$mv=1;
				}
				$aantal[$compNum] = ($aantal[$compNum]??0) + $this->berekenAantal($component['Y']);
				if ($componenten[$set]['Y']??0) {
					$set++;
				}
				$componenten[$set]['Y']=$component['Y'];
			}
			$tmpGebruiksEenheid = array();
			if (!empty($component['a'])) {
                $aComp = $component['a'];
				if (!empty($this->lijstGebruiksEenheid->$aComp)) {
					$tmpGebruiksEenheid = (array) $this->lijstGebruiksEenheid->$aComp;
					$textArr[]=($mv?$this->lijstGebruiksEenheid->$aComp->geMeervoud:$this->lijstGebruiksEenheid->$aComp->geOms);
				}
				if ($componenten[$set]['a']??0) {
					$set++;
				}
				$componenten[$set]['a']=$tmpGebruiksEenheid;
			}

			if (!empty($component['b'])) {
				$tmpBCode = array();
                $bComp = $component['b'];
				
				if (isset($this->lijstBCodes->$bComp)) {
					$tmpBCode = (array) $this->lijstBCodes->$bComp;
					$textArr[]=$this->lijstBCodes->$bComp->t25Oms;
				}

				if (!$set) {
					$componenten[1]['b'][]=$component['b'];
				} else {
					$componenten[$set]['b'][]=$tmpBCode;
				}

			}

			$compNum++;
		}


		$text = implode(' ',$textArr);
		$data['gebruiksTekst']=$text . $vrijetext;
		$data['componenten']=$componenten;
		//$data['componenten']=$splitCode;
		$data['dosis']=0;
		for ($i=0;$i<=$compNum;$i++) {
			$omreken[$i] = $omreken[$i] ?? 1;
			// if (!$omreken[$i])
			// 	$omreken[$i]=1;

			$data['dosis']= ($data['dosis']??0) + (($aantal[$i]??0) * ($freq[$i]??0) / $omreken[$i]);
		}

		return $data;
		//$this->_view->assign("gebruiksTekst", $text);
		//$this->_view->assign("splitCode", $splitCode);

		// return _JSON_OUTPUT;

	}

	private function berekenAantal($aantal) {
		$result=0;
		if (is_numeric($aantal))
			return $aantal;
		// Er zit een streepje in
		if ($aantal=='-')
			return 0;

		$data = explode('-',$aantal);
		foreach ($data as $num) {
			if ($num > $result) {
				$result = $num;
			}
		}
		if (is_numeric($result))
			return $result;
		return 0;
	}


	function loadTables() {
		$dataJson = file_get_contents('t25data.json');
		$this->lijstBCodes = json_decode($dataJson);

		$dataJson = file_get_contents('zi_gebruikseenheid.json');
		$this->lijstGebruiksEenheid = json_decode($dataJson);

		$dataJson = file_get_contents('zi_tijdeenheid.json');
		$this->lijstTijdsEenheid = json_decode($dataJson);

	}
}
?>