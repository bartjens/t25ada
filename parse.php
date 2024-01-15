<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING );
include ('parse25.php');
include ('adaModel.php');


if (isSet($_REQUEST['t25Code'])) {

	$input = trim($_REQUEST['t25Code']);
	if (preg_match("/^[a-zA-Z0-9 \-\.\,]+$/", $input))
		parse($input);
	else
		echo 'geen geldige input';
}
function parse($input) {
	$t25Parser = new t25Parser();
	$adaModel = new Model_Ada();
	$t25Data = $t25Parser->splitGebruiksCode($input);
	$xml = $adaModel->createADAXML($t25Data);
	print_r($xml);
	echo "\n\n";
	print_r($t25Data);

echo '
	<table>
    <tr>
<th>catNhgNr</th>
<th>t25nr</th>
<th>t25Memo</th>
<th>t25Oms</th>
<th>cDagDeel</th>
<th>cAanvullendeTekst</th>
<th>cZonodig</th>
<th>cToedieningsWeg</th>
<th>cGebruiksPeriodeCriterium</th>
<th>cPatroon</th>
<th>cDuur</th>
<th>cNotReady</th>
</tr>
';
foreach ($adaModel->_bCodes as $line) : ?>
    <tr>
		<td><?php echo $line['catNhgNr'];?></td>
        <td><?php echo $line['t25nr'];?></td>
        <td><?php echo $line['t25Memo'];?></td>
        <td><?php echo $line['t25Oms'];?></td>
        <td><?php echo $line['cDagDeel'];?></td>
        <td><?php echo $line['cAanvullendeTekst'];?></td>
        <td><?php echo $line['cZonodig'];?></td>
        <td><?php echo $line['cToedieningsWeg'];?></td>
        <td><?php echo $line['cGebruiksPeriodeCriterium'];?></td>
        <td><?php echo $line['cPatroon'];?></td>
        <td><?php echo $line['cDuur'];?></td>
        <td><?php echo $line['cNotReady'];?></td>

    </tr>
<?php endforeach;
}