<?php
/**
 * Tectonic (aka Suguru) puzzle
 *
 * PHP version 8
 *
 * @category Puzzle
 * @package  Tectonic
 * @author   Frans-Willem Post (FWieP) <fwiep@fwiep.nl>
 * @license  https://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     https://www.fwiep.nl/
 */
namespace FWieP\Tectonic;
/**
 * Tectonic (aka Suguru) puzzle
 *
 * @category Puzzle
 * @package  Tectonic
 * @author   Frans-Willem Post (FWieP) <fwiep@fwiep.nl>
 * @license  https://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     https://www.fwiep.nl/
 */
class Puzzle
{
    public const MAXVALUE = 5;
    public $strategyBeingApplied = -1;
    private $_width = 0;
    private $_height = 0;
    private $_source = '';
    
    /**
     * Gets the puzzle's source (HTML-escaped)
     * 
     * When an HTTP(s) URL, then an HTML-snippet with a hyperlink is returned
     *
     * @return string
     */
    public function getSource() : string
    {
        $pattern = '!^https?://(?<DOMAIN>[^/]+)!i';
        if (preg_match($pattern, $this->_source, $m) > 0) {
            return sprintf(
                '<a href="%1$s">%2$s</a>',
                $this->_source,
                htmlspecialchars($m['DOMAIN'])
            );
        }
        return htmlspecialchars($this->_source);
    }
    
    /**
     * Array of consecutive steps for solving the puzzle
     * 
     * @var PuzzleStep[]
     */
    private $_solutionSteps = [];
    
    /**
     * Gets the solution's steps
     * 
     * @return PuzzleStep[]
     */
    public function getSolutionSteps() : array
    {
        return $this->_solutionSteps;
    }

    /**
     * Adds a step to the puzzle's solution
     * 
     * @param PuzzleStep $step the step to add
     * 
     * @return void
     */
    public function addSolutionStep(PuzzleStep $step) : void
    {
        $this->_solutionSteps[] = $step;
    }
    
    /**
     * The puzzle's pieces
     * 
     * @var PuzzlePiece[]
     */
    private $_pieces = [];
    
    /**
     * The puzzle's cells
     * 
     * @var PuzzleCell[]
     */
    private $_cells = [];
    
    /**
     * Gets the puzzle's cells
     * 
     * @return PuzzleCell[]
     */
    public function getCells() : array
    {
        return $this->_cells;
    }
    
    /**
     * Creates a new puzzle
     *
     * @param string $xml         the puzzle's XML
     * @param bool   $isRecursive whether this is a recursive call (strat#9)
     */
    public function __construct(string $xml, bool $isRecursive = false)
    {
        $dom = new \DOMDocument();
        $xsdFile = __DIR__.'/../puzzles/TectonicPuzzles.xsd';
        
        if (!$dom->loadXML($xml) || !$dom->schemaValidate($xsdFile)) {
            die("The puzzle's XML could not be parsed. Exiting.");
        }
        $xPath = new \DOMXPath($dom);
        $xP = $xPath->query('//Puzzle')->item(0);
        $pWidth = intval($xP->attributes->getNamedItem('width')->nodeValue);
        $pHeight = intval($xP->attributes->getNamedItem('height')->nodeValue);
        
        $pSourceURL = $xP->attributes->getNamedItem('source');
        $pSourceURL = (is_null($pSourceURL) ? "Onbekend"
            : $pSourceURL->nodeValue);
        
        $cells = $xPath->query('./Cell', $xP);
        
        if ($cells->length != ($pWidth * $pHeight)) {
            die("The puzzle's dimensions and cell count don't match. Exiting.");
        }
        $piecesCount = 0;
        foreach ($xPath->query('./Cell/@PieceNumber', $xP) as $nr) {
            $nr = intval($nr->nodeValue);
            $piecesCount = ($nr > $piecesCount ? $nr : $piecesCount);
        }
        $this->_width = $pWidth;
        $this->_height = $pHeight;
        $this->_source = $pSourceURL;
        
        for ($i = 1; $i <= $piecesCount; $i++) {
            $this->_pieces[$i] = new PuzzlePiece($this, $i);
        }
        foreach ($cells as $ix => $c) {
            $nr = intval($c->attributes->getNamedItem('PieceNumber')->nodeValue);
            
            $options = $c->attributes->getNamedItem('Options');
            if ($options) {
                $options = $options->nodeValue;
            }
            $value = $c->attributes->getNamedItem('Value');
            if ($value) {
                $value = $value->nodeValue;
            }
            $piece = $this->_pieces[$nr];
            $cell = new PuzzleCell($piece);
            
            if (!empty($options)) {
                $cell->value = $options;
            }
            if (!empty($value)) {
                $cell->value = $value;
            }
            $piece->cells[] = $cell;
            $this->_cells[] = $cell;
        }
        if (!$isRecursive) {
            foreach ($this->_cells as $ix => $c) {
                if (strlen($c->value) == 1) {
                    continue;
                }
                $c->value = implode('', range(1, count($c->piece->cells)));
            }
        }
        $this->_hookupNeighbours();
    }
    
    /**
     * Hooks up all cell's neighbours as an array of referenced cells
     * 
     * Neighbours are indexed in the following order:
     * ```plain
     * 0 1 2
     * 3 X 5
     * 6 7 8
     * ```
     * where X is the current cell
     * 
     * @return void
     */
    private function _hookupNeighbours() : void
    {
        foreach ($this->_cells as $ix => $c) {
            
            // Loop through all cells to find neighbour cells
            $rowIndex = $this->_getRowIndex($ix);
            
            // top left
            $neighbourIx = $ix - 1 - $this->_width;
            $neighbourRowIndex = $this->_getRowIndex($neighbourIx);
            if ($neighbourRowIndex != -1 && $neighbourRowIndex == $rowIndex - 1) {
                $c->neighbourCells[0] = $this->_cells[$neighbourIx];
            }
            // top
            $neighbourIx = $ix - $this->_width;
            $neighbourRowIndex = $this->_getRowIndex($neighbourIx);
            if ($neighbourRowIndex != -1 && $neighbourRowIndex == $rowIndex - 1) {
                $c->neighbourCells[1] = $this->_cells[$neighbourIx];
            }
            // top right
            $neighbourIx = $ix + 1 - $this->_width;
            $neighbourRowIndex = $this->_getRowIndex($neighbourIx);
            if ($neighbourRowIndex != -1 && $neighbourRowIndex == $rowIndex - 1) {
                $c->neighbourCells[2] = $this->_cells[$neighbourIx];
            }
            // right
            $neighbourIx = $ix + 1;
            $neighbourRowIndex = $this->_getRowIndex($neighbourIx);
            if ($neighbourRowIndex != -1 && $neighbourRowIndex == $rowIndex) {
                $c->neighbourCells[5] = $this->_cells[$neighbourIx];
            }
            // bottom right
            $neighbourIx = $ix + 1 + $this->_width;
            $neighbourRowIndex = $this->_getRowIndex($neighbourIx);
            if ($neighbourRowIndex != -1 && $neighbourRowIndex == $rowIndex + 1) {
                $c->neighbourCells[8] = $this->_cells[$neighbourIx];
            }
            // bottom
            $neighbourIx = $ix + $this->_width;
            $neighbourRowIndex = $this->_getRowIndex($neighbourIx);
            if ($neighbourRowIndex != -1 && $neighbourRowIndex == $rowIndex + 1) {
                $c->neighbourCells[7] = $this->_cells[$neighbourIx];
            }
            // bottom left
            $neighbourIx = $ix - 1 + $this->_width;
            $neighbourRowIndex = $this->_getRowIndex($neighbourIx);
            if ($neighbourRowIndex != -1 && $neighbourRowIndex == $rowIndex + 1) {
                $c->neighbourCells[6] = $this->_cells[$neighbourIx];
            }
            // left
            $neighbourIx = $ix - 1;
            $neighbourRowIndex = $this->_getRowIndex($neighbourIx);
            if ($neighbourRowIndex != -1 && $neighbourRowIndex == $rowIndex) {
                $c->neighbourCells[3] = $this->_cells[$neighbourIx];
            }
        }
    }
    
    /**
     * Tries to solve the puzzle
     * 
     * @param int $depth the current recursion depth
     * 
     * @return bool TRUE on success, FALSE on failure
     */
    public function solve(int $depth = 0) : bool
    {
        if ($depth > 9) {
            // Emergency break for stopping recursion
            return false;
        }
        do {
            $previousHTML = $this->toHTML();

            $this->_solveStrategy1();
            $this->_solveStrategy2();
            $this->_solveStrategy3();
            $this->_solveStrategy4();
            $this->_solveStrategy5();
            $this->_solveStrategy6();
            $this->_solveStrategy7();
            $this->_solveStrategy8();
            
        } while ($this->_isValid() && $this->toHTML() != $previousHTML);
        
        if ($this->_isComplete() && $this->_isValid()) {
            return true;
        }
        return $this->_solveStrategy9($depth);
    }
    
    /**
     * Remove solved cells' values from current cells' options
     * 
     * @return void
     */
    private function _solveStrategy1() : void
    {
        $this->strategyBeingApplied = 1;
        foreach ($this->_pieces as $p) {
            foreach ($p->cells as $c) {
                if (strlen($c->value) > 1) {
                    continue;
                }
                foreach ($p->cells as $c2) {
                    if ($c2 === $c) {
                        continue;
                    }
                    if (strlen($c2->value) == 1) {
                        continue;
                    }
                    $c2->removeValue($c->value);
                }
            }
        }
        return;
    }
    
    /**
     * Remove solved cells' values from neighbour cells
     * 
     * @return void
     */
    private function _solveStrategy2() : void
    {
        $this->strategyBeingApplied = 2;
        foreach ($this->_cells as $ix => $c) {
            if (strlen($c->value) == 1) {
                foreach ($c->neighbourCells as $c2) {
                    $c2->removeValue($c->value);
                }
            }
        }
        return;
    }
    
    /**
     * Set cell's value, if no other cell has that value
     * 
     * @return void
     */
    private function _solveStrategy3() : void
    {
        $this->strategyBeingApplied = 3;
        foreach ($this->_pieces as $p) {
            
            $cellCountThisPiece = count($p->cells);
            
            for ($i = 1; $i <= $cellCountThisPiece; $i++) {

                $cellsWithThisOption = array_filter(
                    $p->cells,
                    function (PuzzleCell $c) use ($i) {
                        return strpos($c->value, (string)$i) !== false;
                    }
                );
                if (count($cellsWithThisOption) == 1) {
                    $c = array_shift($cellsWithThisOption);
                    
                    if (strlen($c->value) > 1) {
                        $c->setValue($i);
                    }
                }
            }
        }
        return;
    }
    
    /**
     * Removes possibility from cell if certain neighbours have
     * two same possibilities
     *
     * @return void
     */
    private function _solveStrategy4() : void
    {
        $this->strategyBeingApplied = 4;
        foreach ($this->_cells as $ix => $c) {
            if (strlen($c->value) != 2) {
                continue;
            }
            // ...  Current cell X is north-west partner
            // .XA  or south-east partner (being Y)
            // .BY
            $neighY = $c->getNeighbour(8);
            $neighA = $c->getNeighbour(5);
            $neighB = $c->getNeighbour(7);
            $this->_removeFromTwo($c, $neighY, $neighA, $neighB);
            
            // ...  Current cell X is north-east partner
            // AX.  or south-west partner (being Y)
            // YB.
            $neighY = $c->getNeighbour(6);
            $neighA = $c->getNeighbour(3);
            $neighB = $c->getNeighbour(7);
            $this->_removeFromTwo($c, $neighY, $neighA, $neighB);
        }
        return;
    }
    
    /**
     * Removes possibility from cell if certain neighbours have
     * two same possibilities
     *
     * @return void
     */
    private function _solveStrategy5() : void
    {
        $this->strategyBeingApplied = 5;
        foreach ($this->_cells as $ix => $c) {
            
            // .AB Current cell X is horizontal partner
            // .XY
            // .CD
            $cY = $c->getNeighbour(5);
            $cA = $c->getNeighbour(1);
            $cB = $c->getNeighbour(2);
            $cC = $c->getNeighbour(7);
            $cD = $c->getNeighbour(8);
            $this->_removeFromFour($c, $cY, $cA, $cB, $cC, $cD);
            
            // ... Current cell X is vertical partner
            // AXC
            // BYD
            $cY = $c->getNeighbour(7);
            $cA = $c->getNeighbour(3);
            $cB = $c->getNeighbour(6);
            $cC = $c->getNeighbour(5);
            $cD = $c->getNeighbour(8);
            $this->_removeFromFour($c, $cY, $cA, $cB, $cC, $cD);
        }
        return;
    }
    
    /**
     * Removes paired possibilities from piece's cells if two other cells match
     *
     * @return void
     */
    private function _solveStrategy6() : void
    {
        $this->strategyBeingApplied = 6;
        foreach ($this->_pieces as $nr => $p) {
            foreach ($p->cells as $c) {
                if (strlen($c->value) != 2) {
                    continue;
                }
                $partner = array_filter(
                    $p->cells,
                    function ($c2) use ($c) {
                        return ($c2 !== $c && $c2->value === $c->value);
                    }
                );
                if (!$partner) {
                    continue;
                }
                $partner = array_shift($partner);
                foreach ($p->cells as $c2) {
                    if ($c2 === $c) {
                        continue;
                    }
                    if ($c2 === $partner) {
                        continue;
                    }
                    foreach (str_split($c->value) as $v) {
                        $c2->removeValue($v);
                    }
                }
            }
        }
        return;
    }
    
    /**
     * Removes possibility from cell if certain neighbours have
     * three same possibilities 
     *
     * @return void
     */
    private function _solveStrategy7() : void
    {
        $this->strategyBeingApplied = 7;
        foreach ($this->_cells as $ix => $c) {
            // ... Current cell is top left
            // .XA
            // .BC
            $neighA = $c->getNeighbour(5);
            $neighB = $c->getNeighbour(7);
            $neighC = $c->getNeighbour(8);
            $this->_removeFromThree($c, $neighA, $neighB, $neighC);
            
            // ... Current cell is top right
            // AX.
            // BC.
            $neighA = $c->getNeighbour(3);
            $neighB = $c->getNeighbour(6);
            $neighC = $c->getNeighbour(7);
            $this->_removeFromThree($c, $neighA, $neighB, $neighC);
                        
            // .AB Current cell is bottom left
            // .XC
            // ...
            $neighA = $c->getNeighbour(1);
            $neighB = $c->getNeighbour(2);
            $neighC = $c->getNeighbour(5);
            $this->_removeFromThree($c, $neighA, $neighB, $neighC);
            
            // AB. Current cell is bottom right
            // CX.
            // ...
            $neighA = $c->getNeighbour(0);
            $neighB = $c->getNeighbour(1);
            $neighC = $c->getNeighbour(3);
            $this->_removeFromThree($c, $neighA, $neighB, $neighC);
        }
        return;
    }
    
    /**
     * Removes possibility from cell if certain neighbours have
     * three same possibilities
     *
     * @return void
     */
    private function _solveStrategy8() : void
    {
        $this->strategyBeingApplied = 8;
        foreach ($this->_cells as $ix => $c) {
            // ..A Current cell is left of stack of three
            // .XB
            // ..C
            $neighA = $c->getNeighbour(2);
            $neighB = $c->getNeighbour(5);
            $neighC = $c->getNeighbour(8);
            $this->_removeFromThree($c, $neighA, $neighB, $neighC);
            
            // A.. Current cell is right of stack of three
            // BX.
            // C..
            $neighA = $c->getNeighbour(0);
            $neighB = $c->getNeighbour(3);
            $neighC = $c->getNeighbour(6);
            $this->_removeFromThree($c, $neighA, $neighB, $neighC);
            
            // ... Current cell is on top of row of three
            // .X.
            // ABC
            $neighA = $c->getNeighbour(6);
            $neighB = $c->getNeighbour(7);
            $neighC = $c->getNeighbour(8);
            $this->_removeFromThree($c, $neighA, $neighB, $neighC);
            
            // ABC Current cell is under row of three
            // .X.
            // ...
            $neighA = $c->getNeighbour(0);
            $neighB = $c->getNeighbour(1);
            $neighC = $c->getNeighbour(2);
            $this->_removeFromThree($c, $neighA, $neighB, $neighC);
        }
        return;
    }
    
    /**
     * Removes values from cell enclosed by or adjacent to two same-value cells
     * 
     * @param PuzzleCell $x current cell
     * @param PuzzleCell $y current cell's partner
     * @param PuzzleCell $a first cell to be stripped of current cell's values
     * @param PuzzleCell $b second cell to be stripped of current cell's values
     * 
     * @return void
     */
    private function _removeFromTwo(
        ?PuzzleCell $x, ?PuzzleCell $y, ?PuzzleCell $a, ?PuzzleCell $b
    ) : void {
        if ($x && $y && $a && $b && $y->value == $x->value) {
            foreach (str_split($x->value) as $v) {
                $a->removeValue($v);
                $b->removeValue($v);
            }
        }
        return;
    }
    
    /**
     * Removes values from cell enclosed by a three-cell piece or adjacent to
     * three same-value cells
     * 
     * @param PuzzleCell $x the current cell to remove values from
     * @param PuzzleCell $a the first neighbouring cell
     * @param PuzzleCell $b the second neighbouring cell
     * @param PuzzleCell $c the third neighbouring cell
     * 
     * @return void
     */
    private function _removeFromThree(
        PuzzleCell $x, ?PuzzleCell $a, ?PuzzleCell $b, ?PuzzleCell $c
    ) : void {
        
        if ($a && $b && $c
            && $a->piece === $b->piece
            && $b->piece === $c->piece
            && $x->piece !== $a->piece
        ) {
            for ($i = 1; $i <= self::MAXVALUE; $i++) {
                if (strpos($a->value, (string)$i) === false
                    || strpos($b->value, (string)$i) === false
                    || strpos($c->value, (string)$i) === false
                    || strpos($x->value, (string)$i) === false
                ) {
                    continue;
                }
                $otherCellsWithThisOption = array_filter(
                    $a->piece->cells,
                    function ($c2) use ($a, $b, $c, $i) {
                        return
                            $c2 !== $a
                            && $c2 !== $b
                            && $c2 !== $c
                            && strpos($c2->value, (string)$i) !== false;
                    }
                );
                if (!$otherCellsWithThisOption) {
                    $x->removeValue($i);
                }
            }
        }
        return;
    }
    
    /**
     * Removes values from cell adjacent to two same-value cells
     *
     * @param PuzzleCell $x current cell
     * @param PuzzleCell $y current cell's partner
     * @param PuzzleCell $a first cell to be stripped of current cell's values
     * @param PuzzleCell $b second cell to be stripped of current cell's values
     * @param PuzzleCell $c third cell to be stripped of current cell's values
     * @param PuzzleCell $d fourth cell to be stripped of current cell's values
     *
     * @return void
     */
    private function _removeFromFour(
        ?PuzzleCell $x, ?PuzzleCell $y,
        ?PuzzleCell $a, ?PuzzleCell $b, ?PuzzleCell $c, ?PuzzleCell $d
    ) : void {
        
        if (!$x || !$y) {
            return;
        }
        $this->_removeFromFourTwo($x, $y, $a, $b);
        $this->_removeFromFourTwo($x, $y, $c, $d);
    }
    
    /**
     * Removes values from cell adjacent to two same-value cells
     *
     * @param PuzzleCell $x current cell
     * @param PuzzleCell $y current cell's partner
     * @param PuzzleCell $m first cell to be stripped of current cell's values
     * @param PuzzleCell $n second cell to be stripped of current cell's values
     *
     * @return void
     */
    private function _removeFromFourTwo(
        PuzzleCell $x, PuzzleCell $y, ?PuzzleCell $m, ?PuzzleCell $n
    ) : void {
        if (!$m || !$n) {
            return;
        }
        
        if ($x->piece === $y->piece
            && ($m->piece !== $x->piece || $n->piece !== $x->piece)
        ) {
            for ($i = 1; $i <= self::MAXVALUE; $i++) {
                if (strpos($x->value, (string)$i) !== false
                    && strpos($y->value, (string)$i) !== false
                ) {
                    $otherCellsWithThisValue = array_filter(
                        $x->piece->cells,
                        function ($c) use ($x, $y, $i) {
                            return
                                $c !== $x
                                && $c !== $y
                                && strpos($c->value, (string)$i) !== false;
                        }
                    );
                    if (!$otherCellsWithThisValue) {
                        if ($m->piece !== $x->piece) {
                            $m->removeValue($i);
                        }
                        if ($n->piece !== $x->piece) {
                            $n->removeValue($i);
                        }
                    }
                }
            }
        }
        if ($y->value == $x->value && strlen($x->value) == 2) {
            foreach (str_split($x->value) as $v) {
                $m->removeValue($v);
                $n->removeValue($v);
            }
        }
    }
    
    /**
     * On cells with two options, try each value in turn. On success,
     * use found value
     * 
     * @param int $depth the current recursion depth 
     *
     * @return bool
     */
    private function _solveStrategy9(int &$depth) : bool
    {
        foreach ($this->_cells as $ix => $c) {
            
            if (strlen($c->value) != 2) {
                continue;
            }
            $valuesToTry = str_split($c->value);
            
            foreach ($valuesToTry as $v) {
                $thisXML = $this->toXML();
                $dom = new \DOMDocument();
                $xsdFile = __DIR__.'/../puzzles/TectonicPuzzles.xsd';
                
                if (!@$dom->loadXML($thisXML)
                    || !@$dom->schemaValidate($xsdFile)
                ) {
                    continue;
                }
                $tryPuzzle = new Puzzle($thisXML, true);
                $oldCellValue = $c->value;
                $tryPuzzle->_cells[$ix]->value = $v;
                
                if ($tryPuzzle->solve(++$depth)) {
                    $this->strategyBeingApplied = 9;
                    $step = new PuzzleStep($this->strategyBeingApplied);
                    $step->cellIx = $ix;
                    $step->oldCellValue = $oldCellValue;
                    $step->newCellValue = $v;
                    $this->addSolutionStep($step);
                    
                    $this->_pieces = $tryPuzzle->_pieces;
                    $this->_cells = $tryPuzzle->_cells;
                    $this->_solutionSteps = array_merge(
                        $this->_solutionSteps, $tryPuzzle->_solutionSteps
                    );
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * The puzzle's current state in XML-format
     * 
     * @return string
     */
    public function toXML() : string
    {
        $dom = new \DOMDocument();
        $dom->formatOutput = true;
        
        $root = $dom->createElement('Puzzles');
        $root->setAttribute(
            'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance'
        );
        $root->setAttribute(
            'xsi:noNamespaceSchemaLocation', 'TectonicPuzzles.xsd'
        );
        $dom->appendChild($root);
        
        $pzlNode = $dom->createElement('Puzzle');
        $pzlNode->setAttribute('width', $this->_width);
        $pzlNode->setAttribute('height', $this->_height);
        if (!empty($this->_source)) {
            $pzlNode->setAttribute('source', $this->_source);
        }
        $root->appendChild($pzlNode);
        
        foreach ($this->_cells as $c) {
            $cNode = $dom->createElement('Cell');
            $cNode->setAttribute('PieceNumber', $c->piece->number);
            
            if (strlen($c->value) == 1) {
                $cNode->setAttribute('Value', $c->value);
            } else {
                $cNode->setAttribute('Options', $c->value);
            }
            $pzlNode->appendChild($cNode);
        }
        return $dom->saveXML();
    }
    
    /**
     * The puzzle's current state as HTML snippet
     * 
     * @param bool $includeIDs whether to include IDs, default TRUE
     * 
     * @return string
     */
    public function toHTML(bool $includeIDs = true) : string
    {
        $o = '<div class="puzzle">';
        
        foreach ($this->_cells as $ix => $c) {
            $cellClasses = [];
            
            $rowIndex = intdiv($ix, $this->_width);
            $lastInRow = ($ix % $this->_width == $this->_width - 1);
            $firstCellIndexOfLastRow = count($this->_cells) - $this->_width;
            
            if ($ix < $firstCellIndexOfLastRow) {
                $neighbourCellBottom = $this->_cells[$ix+$this->_width];
                
                if ($neighbourCellBottom->piece !== $c->piece) {
                    $cellClasses[] = 'bb'; // add a solid border-bottom
                }
            }
            if (!$lastInRow && array_key_exists($ix + 1, $this->_cells)) {
                $neighbourCellRight = $this->_cells[$ix+1];
                
                if ($neighbourCellRight->piece !== $c->piece) {
                    $cellClasses[] = 'br'; // add a solid border-right
                }
            }
            if ($ix % $this->_width == 0) {
                $cellClasses[] = 'rowclear';
            }
            $cellOptions = [];
            for ($i = 1; $i <= count($c->piece->cells); $i++) {
                $iIsOption = strpos($c->value, (string)$i) !== false;
                $cellOptions[] = sprintf(
                    '<span class="mini">%s</span>',
                    $iIsOption ? $i : '&nbsp;'
                );
            }
            $cellContent = '&nbsp;';
            if (strlen($c->value) == 1) {
                $cellContent = $c->value;
            } else if ($includeIDs) {
                $cellContent = implode('', $cellOptions);
            }
            $o .= sprintf(
                '<div%3$s%2$s>%1$s</div>',
                $cellContent,
                $cellClasses ? ' class="'.implode(' ', $cellClasses).'"' : '',
                $includeIDs ? ' id="cix'.$ix.'"' : '' 
            );
        }
        $o .= '</div><!-- /.puzzle -->';
        return $o;
    }
    
    /**
     * Calculates the row index of given cell
     * 
     * @param int $cellIndex the cell index to transform
     * 
     * @return int &gt;= 0 on success, -1 on failure
     */
    private function _getRowIndex(int $cellIndex) : int
    {
        if (array_key_exists($cellIndex, $this->_cells)) {
            return intdiv($cellIndex, $this->_width);
        }
        return -1;
    }
    
    /**
     * Whether this puzzle's solution is valid
     * 
     * @return bool
     */
    private function _isValid() : bool
    {
        foreach ($this->_cells as $c) {
            if (empty($c->value)) {
                return false;
            }
            if (strlen($c->value) > 1) {
                continue;
            }
            $neighboursWithSameValue = array_filter(
                $c->neighbourCells,
                function (PuzzleCell $c2) use ($c) {
                    return $c2->value == $c->value;
                }
            );
            if ($neighboursWithSameValue) {
                return false;
            }
        }
        foreach ($this->_pieces as $p) {
            for ($i = 1; $i <= count($p->cells); $i++) {
                $cellsWithThisOption = array_filter(
                    $p->cells,
                    function (PuzzleCell $c) use ($i) {
                        return $c->value == (string)$i;
                    }
                );
                if (count($cellsWithThisOption) > 1) {
                    return false;
                }
            }    
        }
        return true;
    }
    
    /**
     * Whether the puzzle contains no unsure values
     * 
     * @return bool
     */
    private function _isComplete() : bool
    {
        $cellsWithSingleValue = array_filter(
            $this->_cells,
            function (PuzzleCell $c) {
                return strlen($c->value) == 1;
            }
        );
        return count($cellsWithSingleValue) == count($this->_cells);
    }
}
