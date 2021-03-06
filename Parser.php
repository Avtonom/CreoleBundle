<?php

/*
 * This file is part of WikiBundle
 *
 * (c) Christian Hoffmeister <choffmeister.github@googlemail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Thekwasti\WikiBundle;

use Thekwasti\WikiBundle\Tree\Breakline;
use Thekwasti\WikiBundle\Tree\TableRow;
use Thekwasti\WikiBundle\Tree\TableCellHead;
use Thekwasti\WikiBundle\Tree\TableCell;
use Thekwasti\WikiBundle\Tree\Table;
use Thekwasti\WikiBundle\Tree\NoWikiInline;
use Thekwasti\WikiBundle\Tree\ListItem;
use Thekwasti\WikiBundle\Tree\OrderedList;
use Thekwasti\WikiBundle\Tree\UnorderedList;
use Thekwasti\WikiBundle\Tree\Paragraph;
use Thekwasti\WikiBundle\Tree\NoWiki;
use Thekwasti\WikiBundle\Tree\Link;
use Thekwasti\WikiBundle\Tree\HorizontalRule;
use Thekwasti\WikiBundle\Tree\Bold;
use Thekwasti\WikiBundle\Tree\Italic;
use Thekwasti\WikiBundle\Tree\Headline;
use Thekwasti\WikiBundle\Tree\Text;
use Thekwasti\WikiBundle\Tree\Document;
use Thekwasti\WikiBundle\Tree\NodeInterface;

/**
 * Parser
 * 
 * @author Christian Hoffmeister <choffmeister.github@googlemail.com>
 */
class Parser
{
    public function __construct()
    {
        $this->lexer = new Lexer();
    }
    
    public function parse($markup)
    {
        $lexer = new Lexer();
        $tokens = $lexer->lex($markup);
        
        $i = 0;
        $doc = new Document();
        $stack = new Stack();
        $stack->push($doc);
        
        while ($i < count($tokens)) {
            $current = $stack->peek();
            
            if ($current instanceof Document) {
                $this->parseStateDocument($stack, $tokens, $i);
            } else if ($current instanceof Paragraph) {
                $this->parseStateParagraph($stack, $tokens, $i);
            } else if ($current instanceof Headline) {
                $this->parseStateHeadline($stack, $tokens, $i);
            } else if ($current instanceof UnorderedList) {
                $this->parseStateUnorderedList($stack, $tokens, $i);
            } else if ($current instanceof OrderedList) {
                $this->parseStateOrderedList($stack, $tokens, $i);      
            } else if ($current instanceof ListItem) {
                $this->parseStateListItem($stack, $tokens, $i);   
            } else if ($current instanceof Bold) {
                $this->parseStateBold($stack, $tokens, $i);
            } else if ($current instanceof Italic) {
                $this->parseStateItalic($stack, $tokens, $i);
            } else if ($current instanceof Link) {
                $this->parseStateLink($stack, $tokens, $i);
            } else if ($current instanceof NoWiki) {
                $this->parseStateNoWiki($stack, $tokens, $i);
            } else if ($current instanceof NoWikiInline) {
                $this->parseStateNoWikiInline($stack, $tokens, $i);
            } else if ($current instanceof Table) {
                $this->parseStateTable($stack, $tokens, $i);
            } else if ($current instanceof TableRow) {
                $this->parseStateTableRow($stack, $tokens, $i);
            } else if ($current instanceof TableCell || $current instanceof TableCellHead) {
                $this->parseStateTableCell($stack, $tokens, $i);
            } else {
                // @codeCoverageIgnoreStart
                throw new \Exception(sprintf('Unsupported element of type %s', gettype($current) == 'object' ? get_class($current) : gettype($current)));
                // @codeCoverageIgnoreEnd
            }
        }
        
        return $this->postProcess($doc);
    }
    
    private function postProcess(NodeInterface $node)
    {
        if ($node instanceof Headline) {
            $node->setText(preg_replace('/(^[=\s]*)|([=\s]*$)/', '', $node->getText()));
        }
        
        if (count($node->getChildren()) > 0) {
            $children = $node->getChildren();
            $i = 0;
            
            while ($i < count($children)) {
                if ($i < count($children) - 1 && $children[$i] instanceof Text && $children[$i + 1] instanceof Text) {
                    $children[$i]->setText($children[$i]->getText() . $children[$i + 1]->getText());
                    unset($children[$i + 1]);
                    $children = array_values($children);
                } else {
                    $this->postProcess($children[$i]);
                    $i++;
                }
            }

            $node->setChildren($children);
        }
        
        return $node;
    }
    
    private function parseStateDocument(Stack $stack, array $tokens, &$i)
    {
        $current = $stack->peek();
        $type = $tokens[$i]['type'];
        $value = $tokens[$i]['value'];
            
        switch ($type) {
            case Lexer::T_HEADLINE:
                $headline = new Headline(strlen(trim($value)));
                $current->addChild($headline);
                $stack->push($headline);
                $i++;
                break;
            case Lexer::T_LIST_BULLET_ITEM:
                $unorderedList = new UnorderedList(strlen(trim($value)));
                $current->addChild($unorderedList);
                $stack->push($unorderedList);
                break;
            case Lexer::T_LIST_SHARP_ITEM:
                $orderedList = new OrderedList(strlen(trim($value)));
                $current->addChild($orderedList);
                $stack->push($orderedList);
                break;
            case Lexer::T_NOWIKI_OPEN:
                $noWiki = new NoWiki();
                $stack->peek()->addChild($noWiki);
                $stack->push($noWiki);
                $i++;
                break;
            case Lexer::T_HORIZONTAL_RULE:
                $current->addChild(new HorizontalRule());
                $i++;
                break;
            case Lexer::T_EMPTYLINE:
            case Lexer::T_NEWLINE:
                $i++;
                break;
            case Lexer::T_PIPE:
            case Lexer::T_TABLE_CELL_HEAD:
                $table = new Table();
                $stack->peek()->addChild($table);
                $stack->push($table);
                break;
            default:
                $paragraph = new Paragraph();
                $stack->peek()->addChild($paragraph);
                $stack->push($paragraph);
                break;
        }
    }
    
    private function parseStateParagraph(Stack $stack, array $tokens, &$i)
    {
        $current = $stack->peek();
        $type = $tokens[$i]['type'];
        $value = $tokens[$i]['value'];
        
        switch ($type) {
            case Lexer::T_HEADLINE: 
            case Lexer::T_EMPTYLINE:
            case Lexer::T_LIST_BULLET_ITEM:
            case Lexer::T_LIST_SHARP_ITEM:
            case Lexer::T_NOWIKI_OPEN:
            case Lexer::T_PIPE:
            case Lexer::T_TABLE_CELL_HEAD:
            case Lexer::T_HORIZONTAL_RULE:
                $stack->pop();
                break;
            default:
                $this->parseStateInline($stack, $tokens, $i);
                break;
        }
    }
    
    private function parseStateHeadline(Stack $stack, array $tokens, &$i)
    {
        $current = $stack->peek();
        $type = $tokens[$i]['type'];
        $value = $tokens[$i]['value'];
        
        switch ($type) {
            case Lexer::T_NEWLINE:
            case Lexer::T_EMPTYLINE:
                $stack->pop();
                $i++;
                break;
            default:
                $current->setText($current->getText() . $value);
                $i++;
                break;
        }
    }
    
    private function parseStateUnorderedList(Stack $stack, array $tokens, &$i)
    {
        $current = $stack->peek();
        $type = $tokens[$i]['type'];
        $value = $tokens[$i]['value'];
        
        $level = strlen(trim($value));
        $currentLevel = $current->getLevel();
        
        switch ($type) {
            case Lexer::T_LIST_BULLET_ITEM:
                if ($currentLevel == $level) {
                    $listItem = new ListItem();
                    $current->addChild($listItem);
                    $stack->push($listItem);
                    $i++;
                } else if ($stack->has(function($element) use ($level) { return $element instanceof UnorderedList && $element->getLevel() == $level; })) {
                    $stack->pop();
                } else {
                    $unorderedList = new UnorderedList(strlen(trim($value)));
                    $current->addChild($unorderedList);
                    $stack->push($unorderedList);
                }
                break;
            case Lexer::T_LIST_SHARP_ITEM:
                if ($stack->has(function($element) use ($level) { return $element instanceof OrderedList && $element->getLevel() == $level; })) {
                    $stack->pop();
                } else {
                    $orderedList = new OrderedList(strlen(trim($value)));
                    $current->addChild($orderedList);
                    $stack->push($orderedList);
                }
                break;
            default:
                $stack->pop();
                break;
        }
    }
    
    private function parseStateOrderedList(Stack $stack, array $tokens, &$i)
    {
        $current = $stack->peek();
        $type = $tokens[$i]['type'];
        $value = $tokens[$i]['value'];
        
        $level = strlen(trim($value));
        $currentLevel = $current->getLevel();
        
        switch ($type) {
            case Lexer::T_LIST_SHARP_ITEM:
                if ($currentLevel == $level) {
                    $listItem = new ListItem();
                    $current->addChild($listItem);
                    $stack->push($listItem);
                    $i++;
                } else if ($stack->has(function($element) use ($level) { return $element instanceof OrderedList && $element->getLevel() == $level; })) {
                    $stack->pop();
                } else {
                    $orderedList = new OrderedList(strlen(trim($value)));
                    $current->addChild($orderedList);
                    $stack->push($orderedList);
                }
                break;
            case Lexer::T_LIST_BULLET_ITEM:
                if ($stack->has(function($element) use ($level) { return $element instanceof UnorderedList && $element->getLevel() == $level; })) {
                    $stack->pop();
                } else {
                    $unorderedList = new UnorderedList(strlen(trim($value)));
                    $current->addChild($unorderedList);
                    $stack->push($unorderedList);
                }
                break;
            default:
                $stack->pop();
                break;
        }
    }
    
    private function parseStateListItem(Stack $stack, array $tokens, &$i)
    {
        $current = $stack->peek();
        $type = $tokens[$i]['type'];
        $value = $tokens[$i]['value'];
        
        switch ($type) {
            case Lexer::T_NEWLINE:
                $current->addChild(new Text(' '));
                $i++;
                break;
            case Lexer::T_LIST_BULLET_ITEM:
            case Lexer::T_LIST_SHARP_ITEM:
            case Lexer::T_EMPTYLINE:
            case Lexer::T_HEADLINE:
            case Lexer::T_NOWIKI_OPEN:
                $stack->pop();
                break;       
            default:
                $this->parseStateInline($stack, $tokens, $i);
                break;
        }
    }
    
    private function parseStateBold(Stack $stack, array $tokens, &$i)
    {
        $current = $stack->peek();
        $type = $tokens[$i]['type'];
        $value = $tokens[$i]['value'];
        
        switch ($type) {
            case Lexer::T_EMPTYLINE:
            case Lexer::T_HEADLINE:
            case Lexer::T_LIST_BULLET_ITEM:
            case Lexer::T_LIST_SHARP_ITEM:
                $stack->pop();
                break;
            case Lexer::T_BOLD:
                $stack->pop();
                $i++;
                break;
            default:
                $this->parseStateInline($stack, $tokens, $i);
                break;
        }
    }
    
    private function parseStateItalic(Stack $stack, array $tokens, &$i)
    {
        $current = $stack->peek();
        $type = $tokens[$i]['type'];
        $value = $tokens[$i]['value'];
        
        switch ($type) {
            case Lexer::T_EMPTYLINE:
            case Lexer::T_HEADLINE:
            case Lexer::T_LIST_BULLET_ITEM:
            case Lexer::T_LIST_SHARP_ITEM:
                $stack->pop();
                break;
            case Lexer::T_ITALIC:
                $stack->pop();
                $i++;
                break;
            default:
                $this->parseStateInline($stack, $tokens, $i);
                break;
        }
    }
    
    private function parseStateLink(Stack $stack, array $tokens, &$i)
    {
        $current = $stack->peek();
        $type = $tokens[$i]['type'];
        $value = $tokens[$i]['value'];
        
        if ($current->getHasSpecialPresentation() == false) {
            switch ($type) {
                case Lexer::T_PIPE:
                    $current->setHasSpecialPresentation(true);
                    $i++;
                    break;
                case Lexer::T_NEWLINE:
                case Lexer::T_EMPTYLINE:
                    $stack->pop();
                    break;
                case Lexer::T_LINK_CLOSE:
                    $stack->pop();
                    $i++;
                    break;
                default:
                    $current->setDestination($current->getDestination() . $value);
                    $i++;
                    break;
            }
        } else {
            switch ($type) {
                case Lexer::T_NEWLINE:
                case Lexer::T_EMPTYLINE:
                    $stack->pop();
                    break;
                case Lexer::T_LINK_CLOSE:
                    $stack->pop();
                    $i++;
                    break;
                default:
                    $this->parseStateInline($stack, $tokens, $i);
                    break;
            }
        }
    }
    
    private function parseStateNoWiki(Stack $stack, array $tokens, &$i)
    {
        $current = $stack->peek();
        $type = $tokens[$i]['type'];
        $value = $tokens[$i]['value'];
        
        switch ($type) {
            case Lexer::T_NOWIKI_CLOSE:
                $stack->pop();
                $i++;
                break;
            default:
                $current->addChild(new Text($value));
                $i++;
                break;
        }
    }
    
    private function parseStateNoWikiInline(Stack $stack, array $tokens, &$i)
    {
        $current = $stack->peek();
        $type = $tokens[$i]['type'];
        $value = $tokens[$i]['value'];
        
        switch ($type) {
            case Lexer::T_NOWIKI_CLOSE:
            case Lexer::T_NOWIKI_INLINE_CLOSE:
                $stack->pop();
                $i++;
                break;
            case Lexer::T_NEWLINE:
                $current->addChild(new Text(' '));
                $i++;
                break;
            default:
                $current->addChild(new Text($value));
                $i++;
                break;
        }
    }
    
    private function parseStateTable(Stack $stack, array $tokens, &$i)
    {
        $current = $stack->peek();
        $type = $tokens[$i]['type'];
        $value = $tokens[$i]['value'];
        
        switch ($type) {
            case Lexer::T_PIPE:
            case Lexer::T_TABLE_CELL_HEAD:
                $tableRow = new TableRow();
                $current->addChild($tableRow);
                $stack->push($tableRow);
                break;
            case Lexer::T_NEWLINE:
                $i++;
                break;
            case Lexer::T_NOWIKI_OPEN:
            case Lexer::T_EMPTYLINE:
                $stack->pop();
                break;
            default:
                $stack->pop();
                break;
        }
    }
    
    private function parseStateTableRow(Stack $stack, array $tokens, &$i)
    {
        $current = $stack->peek();
        $type = $tokens[$i]['type'];
        $value = $tokens[$i]['value'];
        
        switch ($type) {
            case Lexer::T_PIPE:
                $tableCell = new TableCell();
                $current->addChild($tableCell);
                $stack->push($tableCell);
                $i++;
                break;
            case Lexer::T_TABLE_CELL_HEAD:
                $tableCellHead = new TableCellHead();
                $current->addChild($tableCellHead);
                $stack->push($tableCellHead);
                $i++;
                break;
            default:
                $stack->pop();
                break;
        }
    }
    
    private function parseStateTableCell(Stack $stack, array $tokens, &$i)
    {
        $current = $stack->peek();
        $type = $tokens[$i]['type'];
        $value = $tokens[$i]['value'];
        
        switch ($type) {
            case Lexer::T_PIPE:
            case Lexer::T_TABLE_CELL_HEAD:
                $stack->pop();
                break;
            case Lexer::T_NEWLINE:
            case Lexer::T_EMPTYLINE:
                $tableCell = $stack->pop();
                break;
            default:
                $this->parseStateInline($stack, $tokens, $i);
                break;
        }
    }
    
    private function parseStateInline(Stack $stack, array $tokens, &$i)
    {
        $current = $stack->peek();
        $type = $tokens[$i]['type'];
        $value = $tokens[$i]['value'];
        
        switch ($type) {
            case Lexer::T_NEWLINE:
                $current->addChild(new Text(' '));
                $i++;
                break;
            case Lexer::T_BREAKLINE:
                $current->addChild(new Breakline());
                $i++;
                break;
            case Lexer::T_ESCAPER:
                $current->addChild(new Text($value[1]));
                $i++;
                break;
            case Lexer::T_BOLD:
                if (!$stack->has(function($element) { return $element instanceof Bold; })) {
                    $bold = new Bold();
                    $current->addChild($bold);
                    $stack->push($bold);
                    $i++;
                } else {
                    $stack->pop();
                }
                break;
            case Lexer::T_ITALIC:
                if (!$stack->has(function($element) { return $element instanceof Italic; })) {
                    $italic = new Italic();
                    $current->addChild($italic);
                    $stack->push($italic);
                    $i++;
                } else {
                    $stack->pop();
                }
                break;
            case Lexer::T_NOWIKI_INLINE_OPEN:
                $noWikiInline = new NoWikiInline();
                $current->addChild($noWikiInline);
                $stack->push($noWikiInline);
                $i++;
                break;
            case Lexer::T_LINK_OPEN:
                $link = new Link();
                $current->addChild($link);
                $stack->push($link);
                $i++;
                break;
            case Lexer::T_LINK_CLOSE:
                if ($stack->has(function($element) { return $element instanceof Link; })) {
                    $stack->pop();
                } else {
                    $current->addChild(new Text($value));
                    $i++;
                }
                break;
            default:
                $current->addChild(new Text($value));
                $i++;
                break;
        }
    }
}

