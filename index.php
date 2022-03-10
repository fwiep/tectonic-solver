<?php
/**
 * Main entry point
 *
 * PHP version 8
 *
 * @category EntryPoint
 * @package  Tectonic
 * @author   Frans-Willem Post (FWieP) <fwiep@fwiep.nl>
 * @license  https://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     https://www.fwiep.nl/
 */
use FWieP\Tectonic as FT;

error_reporting(E_ALL);
ini_set('max_execution_time', '3600');
ini_set('display_errors', '1');
ini_set('date.timezone', 'Europe/Amsterdam');
ini_set('intl.default_locale', 'nl-NL');
date_default_timezone_set('Europe/Amsterdam');
setlocale(LC_ALL, array('nl_NL.utf8', 'nl_NL', 'nl', 'dutch', 'nld'));
mb_internal_encoding('UTF-8');

require_once __DIR__.'/vendor/autoload.php';
define('PUZZLE_DIR', __DIR__.'/puzzles');

$pzlFiles = [];
foreach (scandir(PUZZLE_DIR) as $f) {
    if (is_dir($f)) {
        continue;
    }
    if (preg_match('!puzzle(?<NR>\d+)!i', $f, $m) > 0) {
        $pzlFiles[intval($m['NR'])] = $f;
    }
}
$pzl = null;
$chosenPuzzle = 0;
$pzlSource = 'Onbekend';
$unsolvedGrid = '';
$unsolvedGridWithIDs = '';
$solvedGridWithIDs = '';
$steps = [];
$currentStep = 0;
$stepsCount = 0;

if ($_POST && array_key_exists('selPuzzleToSolve', $_POST)) {
    $chosenPuzzle = intval($_POST['selPuzzleToSolve']);
    
    if (array_key_exists($chosenPuzzle, $pzlFiles)) {
        $xmlFile = PUZZLE_DIR.'/'.$pzlFiles[$chosenPuzzle];
        $pzl = new FT\Puzzle(file_get_contents($xmlFile));
        $pzlSource = $pzl->getSource();
        $unsolvedGrid = $pzl->toHTML(false);
        $unsolvedGridWithIDs = $pzl->toHTML(true);
        $solved = $pzl->solve();
        $solvedGridWithIDs = $pzl->toHTML(true);
        $steps = $pzl->getSolutionSteps();
        $stepsCount = count($steps);
    }
}
$pzlOptions = '<option value="0">Maak een keuze</option>';
foreach ($pzlFiles as $nr => $f) {
    $pzlOptions .= sprintf(
        '<option value="%1$d"%3$s>%2$s</option>',
        $nr,
        $f,
        ($chosenPuzzle == $nr ? ' selected="selected"' : '')
    );
}
$root = str_replace($_SERVER ['DOCUMENT_ROOT'], '', $_SERVER ['SCRIPT_FILENAME']);
$root = str_replace(basename($_SERVER ['SCRIPT_FILENAME']), '', $root);

$isSecure = ($_SERVER['SERVER_PORT'] == 443);
$isSecure = $isSecure ||
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off');

$hrefbase = sprintf(
    '%1$s://%2$s%3$s',
    $isSecure ? 'https' : 'http',
    $_SERVER['HTTP_HOST'],
    $root
);
?><!DOCTYPE html>
<html lang="nl">
<head>
  <base href="<?php print $hrefbase ?>" />
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width,
    initial-scale=1, shrink-to-fit=no">
  <meta name="author" content="Frans-Willem Post" />
  <meta name="robots" content="no-index, no-follow" />
    
  <link
  href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css"
  rel="stylesheet" crossorigin="anonymous"
  integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3"
  />
  <link rel="stylesheet" href="css/main.css" />
  <title>Tectonic puzzel oplossen</title>
</head>
<body>

<div class="container-fluid">

<h1>Tectonic puzzel oplossen</h1>

<p>Deze pagina toont een Suguru- ofwel Tectonic puzzeloplosser. U kunt er voor
kiezen om één van de <?php print count($pzlFiles)?> gegeven puzzels op
te lossen. Het is helaas (nog) niet mogelijk om zelf puzzels in te voeren.</p>
<p>Het programma maakt gebruik van tien strategiën die, zo lang er
vooruitgang wordt geboekt, na elkaar worden toegepast. Meer informatie en
een technische analyse vindt u op <a target="_blank"
href="https://www.fwiep.nl/blog/tectonic-suguru-puzzels-in-php">FWieP's
weblog</a>.</p>

<h2>Uitleg</h2>
<p>
<a href="#collapseUitleg" data-bs-toggle="collapse" role="button"
  aria-expanded="false" aria-controls="collapseUitleg">In- of uitklappen</a>
</p>

<div class="collapse" id="collapseUitleg">

<p>Een Tectonic-puzzel, ook wel Suguru genoemd, bestaat uit een rechthoekig
vlak met cellen. Deze zijn gegroepeerd in puzzelstukken van 1 tot maximaal 5
cellen. In elk puzzelstuk mag een cijfer slechts één keer voorkomen. Ingevulde
cijfers mogen elkaar niet raken; ook niet diagonaal.</p>

<p>De verschillende strategiën zijn als volgt samen te vatten:</p>
<ol>

  <li>Omdat elk cijfer slechts één keer per puzzelstuk voorkomt, kunnen
  gegeven cijfers worden weggestreept in de resterende cellen van dat
  puzzelstuk.
  </li>
  
  <li>Omdat twee gelijke cijfers elkaar niet mogen raken, kunnen die cijfers
  in alle naburige cellen worden weggestreept.
  </li>
  
  <li>Als een bepaald cijfer slechts in één cel van een puzzelstuk voorkomt,
  <em>moet</em> dat cijfer in die cel staan.
  </li>
  
  <li>Hebben twee cellen dezelfde twee cijfers en staan ze diagonaal ten
  opzichte van elkaar, kunnen die cijfers worden weggestreept bij de andere
  twee cellen uit dat vierkant van 2&times;2.
  </li>
  
  <li>Hebben twee cellen dezelfde twee cijfers en staan ze boven (a) of
  naast elkaar (b), kunnen die cijfers worden weggestreept bij de twee linker-
  en rechter (a), of boven- en onderliggende cellen (b).<br />
  Hebben twee boven (a) of naast elkaar (b) staande cellen als enige één
  bepaald cijfer binnen dat puzzelstuk, en is minimaal één naburige cel van
  een ander puzzelstuk, dan kan dat cijfer daar worden weggestreept.
  </li>
  
  <li>Hebben twee cellen in een puzzelstuk dezelfde twee cijfers, kunnen
  die cijfers worden weggestreept bij alle andere cellen van dat puzzelstuk.
  </li>
  
  <li>Wordt een cel (A) omsloten door drie cellen van een ander puzzelstuk (B),
  met in die cellen een cijfer dat in dat puzzelstuk verder niet voorkomt, dan
  kan het cijfer (in A) worden weggestreept.
  </li>
  
  <li>Staat een cel (A) naast, onder of boven drie cellen van een ander
  puzzelstuk (B), met in die cellen een cijfer dat in dat puzzelstuk (B) verder
  niet voorkomt, dan kan het cijfer (in A) worden weggestreept. 
  </li>
  
  <li>Zoek een cel met slechts twee opties en probeer ze stuk voor stuk (begin
  dus weer met strategie 1). Komt er zo een geldige en volledig ingevulde
  puzzel uit, dan was de keuze juist.
  </li>

</ol>

</div><!-- /.collapse -->

<form method="post" action="<?php print htmlspecialchars($_SERVER['PHP_SELF'])?>">

<input type="hidden" id="hidPostDetection" name="hidPostDetection" value="dummy" />

<div class="row justify-content-md-center">

<div class="col-auto">
  <h2>Opgave</h2>
  <?php print $unsolvedGrid ?>
</div>

<div class="col-auto">
  
  <div class="row form-group">
    <div class="col-12">
      <select id="selPuzzleToSolve" name="selPuzzleToSolve">
      <?php print $pzlOptions ?>
      </select>
      <input type="submit" class="btn btn-primary" value="Kies en los op!" />
    </div>
  </div>
  
  <div class="row form-group">
    <div class="col-12">
      <strong>Bron:</strong>
        <span><?php print $pzlSource ?></span>
    </div>
  </div>

  <hr />
  
  <h2>Stappen</h2>
  <p>Totaal aantal: <?php print $stepsCount?></p>
  <p>Huidige stap: <span id="spn-current-step"><?php
      print $currentStep?></span>, strategie <span id="spn-strat"><?php
      print 0 ?></span></p>

    <input type="button" id="btn-min" class="btn btn-primary" value="-" />
    <input type="range" id="rng-steps" min="0" value="<?php print $currentStep ?>"
      step="1" max="<?php print $stepsCount?>" />
    <input type="button" id="btn-plus" class="btn btn-primary" value="+" />
  
</div><!-- /.col-auto -->

<div class="col-auto">
  <h2>Oplossing</h2>
  <div id="div-solution">
    <?php print $solvedGridWithIDs ?>
  </div>
</div><!-- /.col-auto -->

</div><!-- /.row -->

</form>

</div><!-- /.container -->

<script
src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.slim.min.js"
integrity="sha384-Qg00WFl9r0Xr6rUqNLv1ffTSSKEFFCDCKVyHZ+sVt8KuvG99nWw5RNvbhuKgif9z"
crossorigin="anonymous"></script>

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p"
crossorigin="anonymous"></script>

<script>
//<![CDATA[
  var unsolvedGridWithIDs = <?php print json_encode($unsolvedGridWithIDs) ?>;
  var steps = <?php print json_encode($steps) ?>;
  var currentStep = <?php print $currentStep ?>;
  
  function updateGrid() {
    $('#rng-steps').val(currentStep);
    $('#spn-current-step').html(currentStep);

    var grid = $('#div-solution');
    $(grid).html(unsolvedGridWithIDs);

    if (currentStep == 0) {
          $('#spn-strat').html('0');
          return;
      }
      for (var i = 0; i < currentStep; i++) {
        var step = $(steps[i]).get(0);
        var strat = step.strategy;
        var div = $('#cix'+step.cellIx);
        $(div).empty();
        
        if (step.newCellValue.length == 1) {
            $(div).html(step.newCellValue);
        } else {
            var vals = step.newCellValue.split('').map(Number);
            var maxVal = Math.max.apply(null, vals);

            for (var j = 1; j <= maxVal; j++) {
              var c = (vals.indexOf(j) == -1 ? '&nbsp;' : ''+j);
              $(div).append('<span class="mini">'+c+'</span>');
            }
        }
        if (i == currentStep -1) {
            $(div).addClass('flashit');
            $('#spn-strat').html(strat);
        }
      }
    }
  $(function(){
      $('#btn-min').on('click', function(){
          if (currentStep > 0) {
              currentStep--;
          }
          updateGrid();
      });
      $('#rng-steps').on('input change', function(){
          currentStep = $(this).val();
          updateGrid();
      });
      $('#btn-plus').on('click', function(){
          if (currentStep < <?php print $stepsCount?>) {
              currentStep++;
          }
          updateGrid();
      });
  });
//]]>
</script>
</body>
</html>
