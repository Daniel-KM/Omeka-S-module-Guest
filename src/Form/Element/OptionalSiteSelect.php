<?php declare(strict_types=1);

namespace Guest\Form\Element;

class OptionalSiteSelect extends \Omeka\Form\Element\SiteSelect
{
    /**
     * @see https://github.com/zendframework/zendframework/issues/2761#issuecomment-14488216
     *
     * {@inheritDoc}
     * @see \Laminas\Form\Element\Select::getInputSpecification()
     */
    public function getInputSpecification()
    {
        $inputSpecification = parent::getInputSpecification();
        $inputSpecification['required'] = isset($this->attributes['required'])
            && $this->attributes['required'];
        return $inputSpecification;
    }
}
