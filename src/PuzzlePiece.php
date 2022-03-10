<?php
/**
 * Puzzle piece, collection of puzzle cells
 *
 * PHP version 8
 *
 * @category PuzzlePiece
 * @package  Tectonic
 * @author   Frans-Willem Post (FWieP) <fwiep@fwiep.nl>
 * @license  https://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     https://www.fwiep.nl/
 */
namespace FWieP\Tectonic;
/**
 * Puzzle piece, collection of puzzle cells
 *
 * @category PuzzlePiece
 * @package  Tectonic
 * @author   Frans-Willem Post (FWieP) <fwiep@fwiep.nl>
 * @license  https://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     https://www.fwiep.nl/
 */
class PuzzlePiece
{
    /**
     * The piece's parent puzzle
     * 
     * @var Puzzle
     */
    public $puzzle = null;
    
    /**
     * The piece's number
     * 
     * @var int
     */
    public $number = 0;

    /**
     * The piece's cells
     * 
     * @var PuzzleCell[]
     */
    public $cells = [];
    
    /**
     * Creates a new puzzle piece
     * 
     * @param Puzzle $pzl    the piece's parent puzzle
     * @param int    $number the piece's sequential number
     */
    public function __construct(Puzzle &$pzl, int $number)
    {
        $this->puzzle = $pzl;
        $this->number = $number;
    }
}
