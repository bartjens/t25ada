<?php

/**
 * Model for importing the data.
 *
 * @author Bart Molenaar
 */
class Model_Ada {
	protected $_message = null;
	public $_xml = null;
	private $_snomed = array();

	public $_hl7TijdConversie = array();
	public $_hl7GebruiksEenheid = array();
	public $_hl7GebruiksConversie = array();
	public $_hl7BasisEenheidConversie = array();
	public $_bCodes = array();
	private $bCodeParsed = array();
	private $count =0;

	private $dossierData;
	private $interimDataSet;
	private $xmlGebruiksInstructie;
	private $xmlDoseerInstructie;
	private $xmlToedienSchema;
	private $toedienSchemas;
	private $xmlDosering;
	private $xmlZonodig;
	private $xmlCriterium;
	private $doseerInstructieVolgnummer = 0;
	private $usedT25Instruction;
	private $xmlFrequentie;
	private $xmlFrequentieIsSet;
	private $reParseCode = false;



	public function __construct() {

		// Call parent
		$this->_xml = new DOMDocument( "1.0", "UTF-8" );
		$this->_xml->preserveWhiteSpace = false;
		$this->_xml->formatOutput = true;
		$this->_snomed['weekdagen'] = array(
				'maandag'=>array('code'=>'307145004', 'display'=>'maandag'),
				'dinsdag'=>array('code'=>'307147007', 'display'=>'dinsdag'),
				'woensdag'=>array('code'=>'307148002', 'display'=>'woensdag'),
				'donderdag'=>array('code'=>'307149005', 'display'=>'donderdag'),
				'vrijdag'=>array('code'=>'307150005', 'display'=>'vrijdag'),
				'zaterdag'=>array('code'=>'307151009', 'display'=>'zaterdag'),
				'zondag'=>array('code'=>'307146003', 'display'=>'zondag'));

		$this->_snomed['dagdelen'] = array(
			'ochtend'=>array('code'=>'73775008','display'=>"'s ochtends"),
			'middag'=>array('code'=>'255213009','display'=>"'s middags"),
			'avond'=>array('code'=>'3157002','display'=>"'s avonds"),
			'nacht'=>array('code'=>'2546009','display'=>"'s nachts")
		);

	}

    private function setIdentifiers() {

        $dataJson = file_get_contents('t25data.json');
		$this->_bCodes = json_decode($dataJson,true);

		$dataJson = file_get_contents('zi_gebruikseenheid.json');
		$this->_hl7GebruiksConversie = json_decode($dataJson,true);
        // print_r($this->_hl7GebruiksConversie);

		$dataJson = file_get_contents('zi_tijdeenheid.json');
		$this->_hl7TijdConversie = json_decode($dataJson,true);

        $dataJson = file_get_contents('zi_basiseenheid.json');
		$this->_hl7BasisEenheidConversie = json_decode($dataJson,true);

	}

	public function createADAXML($splitT25Data) {

		$this->setIdentifiers();
		$this->dossierData = $splitT25Data;
		$xmlEnv = $this->createEnvelope();

		$this->addDossier($xmlEnv);

		return htmlspecialchars($this->_xml->saveXML());
	}




	private function createEnvelope() {
		$this->xmlGebruiksInstructie = $this->_xml->createElement( 'gebruiksinstructie' );
		$this->_xml->appendChild ($this->xmlGebruiksInstructie);
		return $this->xmlGebruiksInstructie;
	}

	private function createSimpleNode($xmlParent,$element,$attributes=array(),$text='') {
			if ($text) {
				$xml = $this->_xml->createElement( $element, $text);
			} else {
				$xml = $this->_xml->createElement( $element);
			}
			if (!empty($attributes)) {
				foreach ($attributes as $key=>$value) {
					$xml->setAttribute($key,$value);
				}
			}
			$xmlParent->appendChild ($xml);
		return $xml;
	}

	private function createComment($xmlParent,$text) {
			$xml = $this->_xml->createComment($text);
			$xmlParent->appendChild ($xml);
		return $xml;
	}

	private function removeChildren($parent) {
		$count = $parent->childNodes->length;
		for ($i = 0; $i < $count; $i++) {
			$oldNode = $parent->removeChild($parent->childNodes->item(0));
		}
	}

	private function removeElement($xmlElement) {
		$xmlElement->parentNode->removeChild($xmlElement);

	}

	private function addDossier($xmlParent) {

		foreach ($this->dossierData['componenten'] as $regel) {
			$this->usedT25Instruction = $regel;
			$this->doseerInstructieVolgnummer++;
			$this->addDossierRegel($xmlParent,$regel);
		}
	}

	private function addDossierRegel($xmlParent,$regel) {

        $this->xmlDoseerInstructie = $this->createSimpleNode($xmlParent,'doseerinstructie');
		$this->xmlDosering = $this->createSimpleNode($this->xmlDoseerInstructie,'volgnummer',array('value'=>$this->doseerInstructieVolgnummer));
        $this->xmlDosering = $this->createSimpleNode($this->xmlDoseerInstructie,'dosering');

		$this->addDoseQuantity($this->xmlDosering, $regel);
        $xmlToedienSchema = $this->createSimpleNode($this->xmlDosering,'toedieningsschema');
		//toedieningsschema is een array om meerdere referenties naar toedienschemas op te slaan.
		//Dit omdat de dagen bij elke toedienschema wordt geincludeerd
		$this->toedienSchemas[]= $xmlToedienSchema;
		$this->xmlToedienSchema = $xmlToedienSchema;
		$this->xmlFrequentie = $this->createSimpleNode($this->xmlToedienSchema,'frequentie');
		$this->xmlFrequentieIsSet = true;


        $this->parseXComp($regel['X']??'');
		$timeData = $this->parseTimePart($regel);
		if (!empty($regel['b']))
			$bCodeInfo = $this->checkBCodes($regel['b']);
	}



	private function parseXComp($freq) {
		$result=array();
		if (($freq===0) || ($freq=='') || ($freq=='-')) {
			return;
		}

		if (empty($this->xmlFrequentie))
			if (!$this->xmlFrequentieIsSet)
				$this->xmlFrequentie = $this->createSimpleNode($this->xmlToedienSchema,'frequentie');

		$this->xmlFrequentieIsSet=true;

		$xmlAantal = $this->createSimpleNode($this->xmlFrequentie,'aantal');
		if (strpos($freq,'-')) {
			//1-2 x daags
			$xPartArr = explode('-',$freq);
            $this->createSimpleNode($xmlAantal,'minimum_waarde',array('value'=>$xPartArr[0]));
            $this->createSimpleNode($xmlAantal,'maximum_waarde',array('value'=>$xPartArr[1]));
		} else {
            $this->createSimpleNode($xmlAantal,'nominale_waarde',array('value'=>$freq));
        }
        return $result;
	}

	private function parseTimePart($comp) {

		$X = $comp['X']??1;
		$Y = $comp['Y']??'';
		$t = $comp['t']??'';
		if (empty($comp['t'])) {
			return;
		}
		$memoCode = $t['teMemo']??'';

		$omreken = $this->_hl7TijdConversie[$memoCode]['adaTijdValue'];
		$unit = $this->_hl7TijdConversie[$memoCode]['adaTijdUnit'];

		if ($unit =='uur' && $omreken==1) {

			$this->xmlFrequentieIsSet=false;
			$this->removeElement($this->xmlFrequentie);
			if ($X<=1)
				$this->createSimpleNode($this->xmlToedienSchema,'interval',array('value'=>$omreken, 'unit'=>$unit));
			if ($X>1) {
				$this->createSimpleNode($this->xmlToedienSchema,'interval',array('value'=>60/$X, 'unit'=>'minuut'));
			}
		} elseif ($unit =='uur' && $omreken>1) {

			$this->xmlFrequentieIsSet=false;
			$this->removeElement($this->xmlFrequentie);

			$this->createSimpleNode($this->xmlToedienSchema,'interval',array('value'=>$omreken/$X, 'unit'=>$unit));

		}
		else {
			if (!$this->xmlFrequentieIsSet)
				$this->xmlFrequentie = $this->createSimpleNode($this->xmlToedienSchema,'frequentie');
			$this->xmlFrequentieIsSet=true;
			if (!$omreken) $omreken = 1;
			if (!$unit) $unit='dag';
			$xmlTijdsEenheid = $this->createSimpleNode($this->xmlFrequentie,'tijdseenheid',array('value'=>$omreken,'unit'=>$unit));
		}
	}

	private function addDoseQuantity($xmlParent, $regel) {

		$dosY = $regel['Y']??'';
		$dosa = $regel['a']??'';
		if ($dosY=='') {
			return '';
		}

		$factor=1;
		if (!empty($dosa['geHoevGS']))
			$factor = $dosa['geHoevGS'];

		$xmlKeerDosis = $this->createSimpleNode($xmlParent,'keerdosis');
		$xmlAantal = $this->createSimpleNode($xmlKeerDosis,'aantal');

		if ($dosY=='-') {
            //Dit kan niet.. maar een gebruiker..
		} else {

			if (strpos($dosY,'-')) {
				$dosYArr = explode('-',$dosY);
				$this->createSimpleNode($xmlAantal,'minimum_waarde',array('value'=>$dosYArr[0] * $factor));
				$this->createSimpleNode($xmlAantal,'maximum_waarde',array('value'=>$dosYArr[1] * $factor));
			} else {
				$this->createSimpleNode($xmlAantal,'nominale_waarde',array('value'=>$dosY * $factor));
			}
		}
		$this->addEenheid($xmlKeerDosis,$this->usedT25Instruction['a']['geMemo']??'');
	}

	private function checkBCodes($dosb) {

		// echo "\ncheckBCodes";
		// print_r($dosb);
        /*
            check de bcode set op categorie 108: Dagdelen.
            Als er een dagdeel tussen staat die niet gecodeerd kan worden, dan alles in aanvullende tekst.
            Anders bij elkaar vegen.. en de resterende codes apart afhandelen.
        */
        $allesAanvullend=0;
        $dagDeelCodes = array();

        foreach ($dosb as $bCodeOrderId=>$bCode) {
            if ($bCode['catNhgNr'] == 108) {
                if ($bCode['cAanvullendeTekst'] == 1) {
					//Als er 1 in de categorie aanvullend is (en niet gecodeerd kan worden) dan alles aanvullend maken.
                    $allesAanvullend=1;
                }
            }
        }

        if ($allesAanvullend) {
            foreach ($dosb as $bCodeOrderId=>$bCode) {
                if ($bCode['catNhgNr'] == 108) {
                    $dosb[$bCodeOrderId]['cAanvullendeTekst'] = 1;
                }
            }
        }

		foreach ($dosb as $bCodeOrderId=>$bCode) {
			if (!is_array($bCode))
				continue;
			$memoCode = $bCode['t25Memo']??'';
			if ($bCode['vervallen']>0) {
				$this->createComment($this->xmlGebruiksInstructie,'vervallen b-code');
			};

			if ($bCode['cNotReady']) {
				$this->createComment($this->xmlGebruiksInstructie,'tabel25 conversie: nog niet uitgewerkt');
			}

			$stopParsing = $this->verwerkBcodeRest($bCodeOrderId,$bCode);
			if ($stopParsing) {
				return;
			}
			// echo "\nAddDagDeel";
			if (!empty($bCode['cDagDeel']))
				$this->addDagdeel($bCode['cDagDeel']);

			if ($bCode['cPatroon'] !='') {
				$this->verwerkPatroon($bCode);
				break;
			}
		}
	}

	private function verwerkBcodeRest($bCodeOrderId,$bCode) {
		$stopParsing = 0;
		$cat = $bCode['catNhgNr'];
		$memoCode = $bCode['t25Memo']??'';

		// echo "\nverwerkBcodeRest";
		// print_r($bCode);

		switch ($cat) {

			case 101 : //			108 - Dagdelen en gebruik per dagdeel

				$this->parseMemoCodeDagdelen($bCodeOrderId,$bCode);
				$stopParsing=1;

			break;

			case 103:
				$this->addZonodig($bCode);
				break;

			case 107 : //			107 - Wisselend gebruik in de tijd

/*
DOX	1e dag 2, daarna 1 maal daags 1
MWV	Maandag, woensdag en vrijdag --> Via dagen
1W1D1	In de eerste week 1 maal daags 1
22111	2 dagen 2 per dag, daarna 1 per dag
2Z2U1	2 tegelijk, daarna zo nodig om de 2 uur 1
1N2WH	1 nu, na 14 dagen herhalen
3D3WH	3 dagen; eventueel na 3 weken herhalen
DDZ	Op dinsdag, donderdag en zaterdag --> Via dagen
1KDUB	Eerste maal een dubbele dosis
3W1WS	Gedurende 3 weken, daarna 1 week stoppen
1W3WS	Gedurende 1 week, daarna 3 weken stoppen
*/

		switch ($memoCode) {
					case 'DOX':
						//1e dag 2, daarna 1 maal daags 1
						$nieuweBcode = '1d2t 1d1t';

						break;
					case '1W1D1':
						break;
					case '22111':
						$nieuweBcode = '2d2t 1d1t';
						break;
					case '2Z2U1':
						break;
					case '1N2WH':
						break;
					case '3D3WH':
						break;
					case '1KDUB':
						break;
					case '3W1WS':
						//stop week toevoegen
						//Voeg 3 weken toe
						// $this->createSimpleNode($this->xmlDoseerInstructie,'doseerduur',array('value'=>3, 'unit'=>'week'));
						// $this->createSimpleNode($xmlGebruiksInstructie,'doseerduur',array('value'=>3, 'unit'=>'week'));


						break;
					case '1W3WS':
						break;

				}
			break;
			case 108 : //			108 - Dagdelen en gebruik per dagdeel
				if ($this->reParseCode)
					break;
				$this->parseMemoCodeDagdelen($bCodeOrderId,$bCode);
				$stopParsing=1;

			break;
			case 109 : //			109 - Periode aanduiding
				//Wordt als aanvullende tekst opgenomen

			break;
			case 110 : //			110 - Duur aanduiding
			break;
			case 111 : //			111 - Overigen
			break;
			case 112 : //			112 - Herhalen
				if (!$bCode['cAanvullendeTekst']) {
					$tmpCode = array('t25Memo'=>'OTH','t25nr'=>'OTH','t25Oms'=>'overig','originalText' => $bCode['t25Oms']);
					$this->addZonodig($tmpCode);
				}
			break;
			case 113 : //			113 - Maximaal aanduidingen
			break;
			case 114 : //			114 - Weekdagen
			break;
			case 115 : //			115 - Gebruik
			break;
			case 116 : //			116 - Administratief
			break;
			case 117 : //			117 - Adviezen en waarschuwingen
			break;
			case 118 : //			118 - Houdbaarheid
			break;
			case 119 : //			119 - Bereidingsadviezen
			break;
		}

		if ($bCode['cAanvullendeTekst'] == 1) {
			$this->addAsAanvullendeTekst($bCode);
		}
		return $stopParsing;
	}

	private function verwerkPatroon($bCode) {
		$patroon = explode(';',$bCode['cPatroon']);
		if ($patroon[0]=='maxdosering') {
			$this->addMaxDosering($bCode,$patroon);
		}
		if ($patroon[0]=='duur') {
			$this->createSimpleNode($this->xmlDoseerInstructie,'doseerduur',array('value'=>$patroon[1], 'unit'=>$patroon[2]));
		}
		if ($patroon[0]=='nodosage') {
			// verwijder alles
		}
		if ($patroon[0]=='gvt') {
			$this->addAsAanvullendeTekst($bCode);
			$this->createComment($this->xmlGebruiksInstructie,'trombose schema in WDS bij eerste MA, niet de volgende!!');

			$this->removeElement($this->xmlDoseerInstructie);


		}

	}

	private function addMaxDosering($bCode,$patroonParams) {

		/* ************
		TODO

		Netjes maken

		**********   */
		//voeg een zonodig to als die nog niet bestaat
		// if (empty($this->xmlZonodig))
		// 	$this->xmlZonodig = $this->createSimpleNode($this->xmlDosering,'zo_nodig');

		$xmlMaxDos=$this->createSimpleNode($this->xmlDosering,'maximale_dosering');
		$this->createSimpleNode($xmlMaxDos,'aantal', array('value'=>$patroonParams[1]));

		$this->addEenheid($xmlMaxDos,$this->usedT25Instruction['a']['geMemo']);

		$this->createSimpleNode($xmlMaxDos,'tijdseenheid', array('value'=>$patroonParams[2], 'unit'=>$patroonParams[3]));



		// 	<maximale_dosering>
		// 	<aantal value="6"/>
		// 	<eenheid code="245"
		// 			 displayName="stuk"
		// 			 codeSystem="2.16.840.1.113883.2.4.4.1.900.2"
		// 			 codeSystemName="G-Standaard thesaurus basiseenheden"/>
		// 	<tijdseenheid value="1" unit="dag"/>
		//  </maximale_dosering>

	}

	private function parseMemoCodeDagdelen($bCodeOrderId,$bCode) {
		// print_r($this->usedT25Instruction);

		if ($bCode['cAanvullendeTekst']) {
			return;
		}
		// print_r($bCode);
		$memoCode = $bCode['t25Memo'];
		$count=0;
		$dagdeelCount=0;
		$dagDeelMoment = array('MO','MI','AV');
		$oriBcodes = $this->usedT25Instruction['b'];
		if (strpos($memoCode,'-')) {
			$dagDelen = explode('-',$memoCode);
			//print_r($dagDelen);
			//Ochtend
			//voeg per item een
			//voeg doseerinstructie toe
			//Neem de x en de t over
			foreach ($dagDelen as $dagDeelHoeveelheid) {
				if ($dagDeelHoeveelheid=='H')
					$dagDeelHoeveelheid = 0.5;
				if ($dagDeelHoeveelheid>0) {
					$newDos = array();
					$newDos['X'] = '-'; //$this->usedT25Instruction['X'];
					$newDos['t'] = $this->usedT25Instruction['t']??'';
					$newDos['a'] = $this->usedT25Instruction['a']??'';
					$newDos['Y']= $dagDeelHoeveelheid;
					$newDos['b'][]= $this->_bCodes[$dagDeelMoment[$dagdeelCount]];

					if ($count==0) {
						$this->removeElement($this->xmlDoseerInstructie);
						// $this->removeChildren($this->xmlGebruiksInstructie);
					}
					$count++;
					// print_r($newDos);
					$this->usedT25Instruction = $newDos;
					$this->doseerInstructieVolgnummer=1;
					$this->reParseCode = true;
					$this->addDossierRegel($this->xmlGebruiksInstructie,$newDos);
				}
				$dagdeelCount++;
			}

			$this->reParseCode = false;
			unset($oriBcodes[$bCodeOrderId]);
			if (count($oriBcodes)) {
				$this->checkBCodes($oriBcodes);
			}
		} else {
			if ($bCode['cDagDeel']) {
				//controleer de dagdelen vs X-deel
				$dagdelen = explode(',',$bCode['cDagDeel']);
				$aantalDagdelenInBcode = count($dagdelen);
				$aantalInXComp = $this->usedT25Instruction['X']??'';
				// echo "\n" . '$aantalDagdelenInBcode ' . $aantalDagdelenInBcode;
				// echo "\n" . '$aantalInXComp ' . $aantalInXComp;
				if ($aantalDagdelenInBcode != $aantalInXComp && $aantalInXComp !='-') {
					//voeg toe als vrije tekst
					$this->createComment($this->xmlGebruiksInstructie,'Fout in de tabel25 code, dagdelen in a-deel is ongelijk aan b-code');
					$this->addAsAanvullendeTekst($bCode);
					return;
				}
				foreach ($dagdelen as $dagdeel) {

						$newDos = array();
						$newDos['X'] = $this->usedT25Instruction['X'];
						$newDos['t'] = $this->usedT25Instruction['t']??'';
						if ($newDos['t']=='D') {
							$newDos['X'] = '';
							$newDos['t'] = '';

						}
						$newDos['a'] = $this->usedT25Instruction['a']??'';
						$newDos['Y']= $this->usedT25Instruction['Y']??'';
						// $newDos['b'][]= $this->_bCodes[$dagDeelMoment[$dagdeelCount]];



						if ($count==0) {
							$this->removeChildren($this->xmlGebruiksInstructie);
						}
						$count++;
						$this->usedT25Instruction = $newDos;
						$this->doseerInstructieVolgnummer=1;
						$this->addDossierRegel($this->xmlGebruiksInstructie,$newDos);


					$dagdeel = trim(strtolower($dagdeel));
					switch ($dagdeel) {
						case 'ochtend':
						case 'middag':
						case 'avond':
						case 'nacht':
                            $this->removeElement($this->xmlFrequentie);
							//Kennelijk moeten deze aan alle toedienschema's worden gekoppeld.

							$this->createSimpleNode($this->xmlToedienSchema,'dagdeel',
									array(
										'code'=>$this->_snomed['dagdelen'][$dagdeel]['code'],
										'codeSystem'=>'2.16.840.1.113883.6.96',
										'displayName'=>$this->_snomed['dagdelen'][$dagdeel]['display'])
								);
							break;
					}
				}
			}
		}
	}


	private function addAsAanvullendeTekst($bCodeSet) {
		// print_r($bCodeSet);
		//als reeds toegevoegd skippen
		if (isset($this->bCodeParsed[$bCodeSet['t25nr']]))
			return;

		if ($bCodeSet['code']??''=='OTH') {
			$this->createSimpleNode($this->xmlGebruiksInstructie,'aanvullende_instructie',$bCodeSet);
			return;
		}
		$this->bCodeParsed[$bCodeSet['t25nr']] = 1;
		$this->createSimpleNode($this->xmlGebruiksInstructie,'aanvullende_instructie',
			array(
				'code'=>$bCodeSet['t25nr'],
				'codeSystem'=>'2.16.840.1.113883.2.4.4.5',
				'codeSystemName'=>'NHG tabel 25 aanvullende tekst',
				'displayName'=>$bCodeSet['t25Oms'])
		);

		// <aanvullende_instructie code="OTH"
        //                                   codeSystem="2.16.840.1.113883.5.1008"
        //                                   displayName="overig"
        //                                   originalText="Volgens schema trombosedienst"/>


	}

	private function addDagdeel($dagdeelInput) {

		if ($dagdeelInput=='')
			return;
		$dagdelen = explode(',',$dagdeelInput);
		foreach ($dagdelen as $dagdeel) {
			$dagdeel = trim(strtolower($dagdeel));
			switch ($dagdeel) {
				case 'ochtend':
				case 'middag':
				case 'avond':
				case 'nacht':

					$this->removeElement($this->xmlFrequentie);
					$this->createSimpleNode($this->xmlToedienSchema,'dagdeel',
							array(
								'code'=>$this->_snomed['dagdelen'][$dagdeel]['code'],
								'codeSystem'=>'2.16.840.1.113883.6.96',
								'displayName'=>$this->_snomed['dagdelen'][$dagdeel]['display'])
						);
					break;

				case 'maandag' :
				case 'dinsdag' :
				case 'woensdag':
				case 'donderdag':
				case 'vrijdag':
				case 'zaterdag':
				case 'zondag':
					// print_r($this->toedienSchemas);
					//We moeten de dagen voor elk toedienschema toevoegen
					foreach ($this->toedienSchemas as $xmlToedienSchema) {
						if (isset($xmlToedienSchema->tagName)) {
							$this->createSimpleNode($xmlToedienSchema,'weekdag',
							array(
								'code'=>$this->_snomed['weekdagen'][$dagdeel]['code'],
								'codeSystem'=>'2.16.840.1.113883.6.96',
								'displayName'=>$this->_snomed['weekdagen'][$dagdeel]['display'])
								);
						}
					}
					break;
			}
		}

	}

	private function addZonodig($bCodeSet='') {
		// print_r($bCodeSet);
		if (empty($this->xmlZonodig))
			$this->xmlZonodig = $this->createSimpleNode($this->xmlDosering,'zo_nodig');

		if ($bCodeSet=='')
			return;

		if ($bCodeSet['t25Memo']) {
			// if (empty($this->xmlCriterium)) // Weggehaald omdat er altijd een complete dubbel tak moet zijn
				$this->xmlCriterium = $this->createSimpleNode($this->xmlZonodig,'criterium');
			$attrib = array(
				'code'=>$bCodeSet['t25nr'],
				'codeSystem'=>'2.16.840.1.113883.2.4.4.5',
				'displayName'=>$bCodeSet['t25Oms']
			);
			if (isset($bCodeSet['originalText']))
				$attrib['originalText'] = $bCodeSet['originalText'];

			$this->createSimpleNode($this->xmlCriterium,'criterium',$attrib);
		}
	}

	private function addStopweken($aantalWeken) {


	}

	private function addValueQuantity($xmlParent,$tag,$value,$eenheidGS) {

		if ($this->_hl7BasisEenheidConversie[$eenheidGS]['hl7c']) {
			$xmlValueQuantity = $this->createSimpleNode($xmlParent,$tag,array('value'=>$value,'unit'=>$this->_hl7BasisEenheidConversie[$eenheidGS]['hl7c']));
		}
		else {
			$xmlValueQuantity = $this->createSimpleNode($xmlParent,$tag,array('value'=>$value));
		}
		$this->addDoseQuantityTranslation($xmlValueQuantity,$eenheidGS,$value);
		//$this->printXML($xmlValueQuantity);
	}
//
	private function addEenheid($xmlParent,$geMemo) {
		// echo '$geMemo' . $geMemo;
		if ($geMemo=='')
			return;
		if (empty($this->_hl7GebruiksConversie[$geMemo]))
			return;
		if (empty($this->_hl7GebruiksConversie[$geMemo]['geEenheidGSNum']))
			return;

		$xmlValueDoseQuantity = $this->createSimpleNode($xmlParent,'eenheid',
			array(
				'code'=>$this->_hl7GebruiksConversie[$geMemo]['geEenheidGSNum'],
				'displayName'=>strtolower($this->_hl7GebruiksConversie[$geMemo]['geEenheidGSOms']),
				'codeSystem'=>"2.16.840.1.113883.2.4.4.1.900.2",
				'codeSystemName'=>"G-Standaard thesaurus basiseenheden"
			)
		);


		// $this->addDoseQuantityTranslation($xmlValueDoseQuantity,$geMemo,$value);
	}


}
?>