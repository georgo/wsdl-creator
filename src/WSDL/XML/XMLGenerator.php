<?php
/**
 * XMLWrapperGenerator
 *
 * @author Piotr Olaszewski <piotroo89 [%] gmail dot com>
 * @see http://www.xfront.com/GlobalVersusLocal.html
 */
namespace WSDL\XML;

use DOMDocument;
use WSDL\Parser\MethodParser;
use WSDL\WSDLObject\WSDLObject;

class XMLGenerator
{
    private $_name;
    private $_targetNamespace;
    private $_targetNamespaceTypes;
    /**
     * @var DOMDocument
     */
    private $_DOMDocument;
    /**
     * @var DOMDocument
     */
    private $_definitionsRootNode;
    /**
     * @var DOMDocument
     */
    private $_generatedXML;
    /**
     * @var WSDLObject
     */
    private $_WSDLObject;
    /**
     * @see http://infohost.nmt.edu/tcc/help/pubs/rnc/xsd.html
     */
    private $_parametersTypes = array(
        'string' => 'xsd:string',
        'integer' => 'xsd:int',
        'int' => 'xsd:int'
    );
    private $_location;

    public function __construct($name, $namespace, $location)
    {
        $this->_name = $name;
        $this->_location = $location;

        $this->_targetNamespace = $namespace . strtolower($name);
        $this->_targetNamespaceTypes = $this->_targetNamespace . '/types';

        $this->_DOMDocument = new DOMDocument("1.0", "UTF-8");
        $this->_saveXML();
    }

    public function setWSDLObject(WSDLObject $WSDLObject)
    {
        $this->_WSDLObject = $WSDLObject;
        return $this;
    }

    public function generate()
    {
        $this->_definitions()->_types()->_message()->_portType()->_binding()->_service();
    }

    /**
     * @return XMLGenerator
     */
    private function _definitions()
    {
        $definitionsElement = $this->_createElementWithAttributes('definitions', array(
            'name' => $this->_name,
            'targetNamespace' => $this->_targetNamespace,
            'xmlns:tns' => $this->_targetNamespace,
            'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
            'xmlns:soap' => 'http://schemas.xmlsoap.org/wsdl/soap/',
            'xmlns' => 'http://schemas.xmlsoap.org/wsdl/',
            'xmlns:ns' => $this->_targetNamespaceTypes
        ));
        $this->_DOMDocument->appendChild($definitionsElement);
        $this->_definitionsRootNode = $definitionsElement;
        $this->_saveXML();
        return $this;
    }

    /**
     * @return XMLGenerator
     */
    private function _types()
    {
        $typesElement = $this->_createElement('types');

        $schemaElement = $this->_createElement('xsd:schema');
        $targetNamespaceAttribute = $this->_createAttributeWithValue('targetNamespace', $this->_targetNamespaceTypes);
        $schemaElement->appendChild($targetNamespaceAttribute);
        $xmlnsAttribute = $this->_createAttributeWithValue('xmlns', $this->_targetNamespaceTypes);
        $schemaElement->appendChild($xmlnsAttribute);

        $complexTypes = $this->_WSDLObject->getTypes();
        foreach ($complexTypes as $i => $complex) {
            $elementElement = $this->_createElement('xsd:element');
            $elementNameAttribute = $this->_createAttributeWithValue('name', $complex->getTypeName() . ($i + 1) . 'Input');
            $elementElement->appendChild($elementNameAttribute);

            foreach ($complex->getComplexTypes() as $type) {
                $complexTypeElement = $this->_createElement('xsd:complexType');
                $sequenceElement = $this->_createElement('xsd:sequence');
                foreach ($type as $complexSingle) {
                    $typeElement = $this->_createElement('xsd:element');

                    $typeNameAttribute = $this->_createAttributeWithValue('name', $complexSingle->getName());
                    $typeElement->appendChild($typeNameAttribute);


                    $typeTypeAttribute = $this->_createAttributeWithValue('type', $this->_getXsdType($complexSingle->getType()));
                    $typeElement->appendChild($typeTypeAttribute);

                    $sequenceElement->appendChild($typeElement);
                }
                $complexTypeElement->appendChild($sequenceElement);
            }

            $elementElement->appendChild($complexTypeElement);
            $schemaElement->appendChild($elementElement);

            $elementReturningElement = $this->_createElement('xsd:element');
            $elementReturningNameAttribute = $this->_createAttributeWithValue('name', $complex->getTypeName() . 'Output');
            $elementReturningElement->appendChild($elementReturningNameAttribute);

            $complexTypeReturningElement = $this->_createElement('xsd:complexType');
            $sequenceReturningElement = $this->_createElement('xsd:sequence');
            foreach ($complex->getReturningComplexType() as $returningComplexType) {
                $elementPartReturningElement = $this->_createElement('xsd:element');

                $typeNameReturningAttribute = $this->_createAttributeWithValue('name', $returningComplexType->getName());
                $elementPartReturningElement->appendChild($typeNameReturningAttribute);

                $typeTypeReturningAttribute = $this->_createAttributeWithValue('type', $this->_getXsdType($returningComplexType->getType()));
                $elementPartReturningElement->appendChild($typeTypeReturningAttribute);

                $sequenceReturningElement->appendChild($elementPartReturningElement);
            }
            $complexTypeReturningElement->appendChild($sequenceReturningElement);
            $elementReturningElement->appendChild($complexTypeReturningElement);
            $schemaElement->appendChild($elementReturningElement);
        }

        $typesElement->appendChild($schemaElement);

        $this->_definitionsRootNode->appendChild($typesElement);
        $this->_saveXML();
        return $this;
    }

    /**
     * @return XMLGenerator
     */
    private function _message()
    {
        foreach ($this->_WSDLObject->getMethods() as $method) {
            $messageInputElement = $this->_createElementWithAttributes('message', array(
                'name' => $method->getName() . 'Request'
            ));
            foreach ($this->_createXMLMessageInputParts($method) as $part) {
                $messageInputElement->appendChild($part);
            }
            $this->_definitionsRootNode->appendChild($messageInputElement);

            $messageOutputElement = $this->_createElementWithAttributes('message', array(
                'name' => $method->getName() . 'Response'
            ));
            $messageOutputElement->appendChild($this->_createXMLMessageOutput($method));
            $this->_definitionsRootNode->appendChild($messageOutputElement);
        }
        return $this;
    }

    private function _createXMLMessageInputParts(MethodParser $method)
    {
        $partElements = array();
        foreach ($method->parameters() as $i => $param) {
            if ($param->isComplex()) {
                $type = 'element';
                $value = 'ns:' . $method->getName() . ($i + 1) . 'Input';
            } else {
                $type = 'type';
                $value = $this->_getXsdType($param->getType());
            }
            $partElements[] = $this->_createElementWithAttributes('part', array(
                'name' => $param->getName(),
                $type => $value
            ));
        }
        return $partElements;
    }

    private function _createXMLMessageOutput(MethodParser $method)
    {
        $parameter = $method->returning();
        if ($parameter->isComplex()) {
            $type = 'element';
            $value = 'ns:' . $method->getName() . 'Output';
        } else {
            $type = 'type';
            $value = $this->_getXsdType($parameter->getType());
        }
        $returnElement = $this->_createElementWithAttributes('part', array(
            'name' => $parameter->getName() ? $parameter->getName() : $method->getName() . 'Output',
            $type => $value
        ));
        return $returnElement;
    }

    /**
     * @return XMLGenerator
     */
    private function _portType()
    {
        $portTypeElement = $this->_createElementWithAttributes('portType', array(
            'name' => $this->_name . 'PortType'
        ));

        foreach ($this->_WSDLObject->getMethods() as $method) {
            $operationElement = $this->_createElementWithAttributes('operation', array('name' => $method->getName()));

            if ($method->description()) {
                $documentationElement = $this->_createElement('documentation', $method->description());
                $operationElement->appendChild($documentationElement);
            }

            $inputElement = $this->_createElementWithAttributes('input', array('message' => 'tns:' . $method->getName() . 'Request'));
            $operationElement->appendChild($inputElement);

            $outputElement = $this->_createElementWithAttributes('output', array('message' => 'tns:' . $method->getName() . 'Response'));
            $operationElement->appendChild($outputElement);

            $portTypeElement->appendChild($operationElement);
        }
        $this->_definitionsRootNode->appendChild($portTypeElement);
        return $this;
    }

    /**
     * @return XMLGenerator
     */
    private function _binding()
    {
        $bindingElement = $this->_createElementWithAttributes('binding', array(
            'name' => $this->_name . 'Binding',
            'type' => 'tns:' . $this->_name . 'PortType'
        ));

        $soapBindingElement = $this->_createElementWithAttributes('soap:binding', array(
            'style' => 'rpc',
            'transport' => 'http://schemas.xmlsoap.org/soap/http'
        ));
        $bindingElement->appendChild($soapBindingElement);

        foreach ($this->_WSDLObject->getMethods() as $method) {
            $soapBodyElement = $this->_createElementWithAttributes('soap:body', array(
                'use' => 'literal'
            ));

            $operationElement = $this->_createElementWithAttributes('operation', array(
                'name' => $method->getName()
            ));

            $soapOperationElement = $this->_createElementWithAttributes('soap:operation', array(
                'soapAction' => $this->_targetNamespace . '/#' . $method->getName()
            ));
            $operationElement->appendChild($soapOperationElement);

            $inputElement = $this->_createElement('input');
            $inputElement->appendChild($soapBodyElement);
            $operationElement->appendChild($inputElement);

            $outputElement = $this->_createElement('output');
            $outputElement->appendChild($soapBodyElement->cloneNode());
            $operationElement->appendChild($outputElement);

            $bindingElement->appendChild($operationElement);
        }
        $this->_definitionsRootNode->appendChild($bindingElement);
        $this->_saveXML();
        return $this;
    }

    /**
     * @return XMLGenerator
     */
    private function _service()
    {
        $serviceElement = $this->_createElementWithAttributes('service', array('name' => $this->_name . 'Service'));

        $portElement = $this->_createElementWithAttributes('port', array(
            'name' => $this->_name . 'Port',
            'binding' => 'tns:' . $this->_name . 'Binding'
        ));

        $soapAddressElement = $this->_createElementWithAttributes('soap:address', array('location' => $this->_location));
        $portElement->appendChild($soapAddressElement);

        $serviceElement->appendChild($portElement);
        $this->_definitionsRootNode->appendChild($serviceElement);
        $this->_saveXML();
    }

    private function _createElement($elementName, $value = '')
    {
        return $this->_DOMDocument->createElement($elementName, $value);
    }

    private function _createAttributeWithValue($attributeName, $value)
    {
        $attribute = $this->_DOMDocument->createAttribute($attributeName);
        $attribute->value = $value;
        return $attribute;
    }

    private function _createElementWithAttributes($elementName, $attributes, $value = '')
    {
        $element = $this->_createElement($elementName, $value);
        foreach ($attributes as $attributeName => $attributeValue) {
            $tmpAttr = $this->_createAttributeWithValue($attributeName, $attributeValue);
            $element->appendChild($tmpAttr);
        }
        return $element;
    }

    private function _getXsdType($type)
    {
        return isset($this->_parametersTypes[$type]) ? $this->_parametersTypes[$type] : 'xds:' . $type;
    }

    private function _saveXML()
    {
        $this->_generatedXML = $this->_DOMDocument->saveXML();
    }

    public function render()
    {
        echo $this->_generatedXML;
    }
}