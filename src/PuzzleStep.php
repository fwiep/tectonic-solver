<?php
/**
 * Single step in solving a tectonic puzzle
 *
 * PHP version 8
 *
 * @category PuzzleStep
 * @package  Tectonic
 * @author   Frans-Willem Post (FWieP) <fwiep@fwiep.nl>
 * @license  https://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     https://www.fwiep.nl/
 */
namespace FWieP\Tectonic;

/**
 * Single step in solving a tectonic puzzle
 *
 * @category PuzzleStep
 * @package  Tectonic
 * @author   Frans-Willem Post (FWieP) <fwiep@fwiep.nl>
 * @license  https://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     https://www.fwiep.nl/
 */
class PuzzleStep implements \JsonSerializable
{
    public $cellIx = -1;
    
    public $oldCellValue = '';
    
    public $newCellValue = ''; 
    
    public $strategy = -1;
    
    /**
     * Performs pre-serialization
     * 
     * @return PuzzleStep
     */
    public function jsonSerialize() : PuzzleStep
    {
        return $this;
    }
    
    /**
     * Creates a new instance
     * 
     * @param int $strategy the puzzle strategy being applied
     */
    public function __construct(int $strategy)
    {
        $this->strategy = $strategy;
    }
}
