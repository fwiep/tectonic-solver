<?php
/**
 * Single puzzle cell
 *
 * PHP version 8
 *
 * @category PuzzleCell
 * @package  Tectonic
 * @author   Frans-Willem Post (FWieP) <fwiep@fwiep.nl>
 * @license  https://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     https://www.fwiep.nl/
 */
namespace FWieP\Tectonic;
/**
 * Single puzzle cell
 *
 * @category PuzzleCell
 * @package  Tectonic
 * @author   Frans-Willem Post (FWieP) <fwiep@fwiep.nl>
 * @license  https://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     https://www.fwiep.nl/
 */
class PuzzleCell
{
    /**
     * The cell's parent puzzle piece
     * 
     * @var PuzzlePiece
     */
    public $piece = null;

    /**
     * The cell's value(s)
     * 
     * @var string
     */
    public $value = '';
    
    /**
     * Sets the cell's (single) value
     * 
     * @param int|string $v the (single) value to set
     * 
     * @return bool
     */
    public function setValue($v) : bool
    {
        $v = (string)$v;
        
        $step = new PuzzleStep($this->piece->puzzle->strategyBeingApplied);
        $step->cellIx = array_search(
            $this, $this->piece->puzzle->getCells(), true
        );
        $step->oldCellValue = $this->value;
        
        $this->value = $v;
        
        $step->newCellValue = $this->value;
        $this->piece->puzzle->addSolutionStep($step);
        return true;
    }
    
    /**
     * Removes a specific option from the cell's value
     * 
     * @param int|string $v the single value to remove
     * 
     * @return bool TRUE when the value was present before being removed
     */
    public function removeValue($v) : bool
    {
        $v = (string)$v;

        if ($vIsPresent = (strpos($this->value, $v) !== false)) {
            $step = new PuzzleStep($this->piece->puzzle->strategyBeingApplied);
            $step->cellIx = array_search(
                $this, $this->piece->puzzle->getCells(), true
            );
            $step->oldCellValue = $this->value;
            
            $this->value = str_replace($v, '', $this->value);
            
            $step->newCellValue = $this->value;
            $this->piece->puzzle->addSolutionStep($step);
        }
        return $vIsPresent;
    }
    
    /**
     * The cell's neighbouring cells
     * 
     * @var PuzzleCell[]
     */
    public $neighbourCells = [];
    
    /**
     * Gets the cell's neighbour at the specified index
     * 
     * Neighbours are indexed in the following order:
     * ```plain
     * 0 1 2
     * 3 X 5
     * 6 7 8
     * ```
     * where X is the current cell
     * 
     * @param int $ix the neighbour's 0-based index
     * 
     * @return PuzzleCell|NULL
     */
    public function getNeighbour(int $ix) : ?PuzzleCell
    {
        if (array_key_exists($ix, $this->neighbourCells)) {
            return $this->neighbourCells[$ix];
        }
        return null;
    }
    
    /**
     * Creates a new puzzle cell
     * 
     * @param PuzzlePiece $pc the cell's parent puzzle piece
     */
    public function __construct(PuzzlePiece &$pc)
    {
        $this->piece = $pc;
        $this->value = implode('', range(1, Puzzle::MAXVALUE));
    }
}
