<?php

namespace Thekwasti\WikiBundle\Tree;

class Node implements NodeInterface
{
    protected $children = array();
    
    public function __construct($children = array())
    {
        $this->setChildren($children);
    }
    
    public function addChild(NodeInterface $child)
    {   
        $this->children[] = $child;
    }
    
    public function getChildren()
    {
        return $this->children;
    }
    
    public function setChildren($children)
    {
        if (!is_array($children)) {
            $children = array($children);
        }
        
        foreach ($children as $child) {
            if (!$child instanceof NodeInterface) {
                throw new \InvalidArgumentException(sprintf('$children must be an array of NodeInterface objects. Found an %s element', gettype($child) == 'object' ? get_class($child) : gettype($child)));
            }
        }
        
        $this->children = $children;
    }
    
    public function serialize()
    {
        return serialize(array(
            $this->getChildren()
        ));
    }
    
    public function unserialize($serialized)
    {
        list($this->children) = unserialize($serialized);
    }
}
