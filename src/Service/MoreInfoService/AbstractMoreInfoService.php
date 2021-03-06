<?php

/**
 * @file
 * Abstract SOAP Service that mimics the original 'moreInfo' service.
 *
 * This class was created using wsdl2php. Modified for 'moreInfo' service.
 *
 * @wsdl2php  Wed, 21 Nov 2018 13:11:08 +0100 - Last modified
 * @WSDL      moreinfo.wsdl
 * @Processed Tue, 20 Nov 2018 20:44:22 +0100
 * @Hash      c0e22cf73947f4676ad67c6b82085672
 */

namespace App\Service\MoreInfoService;

use App\Event\SearchNoHitEvent;
use App\Exception\CoverStoreTransformationException;
use App\Service\CoverStore\CoverStoreTransformationInterface;
use App\Service\MoreInfoService\Exception\MoreInfoException;
use App\Service\MoreInfoService\Types\IdentifierInformationType;
use App\Service\MoreInfoService\Types\IdentifierType;
use App\Service\MoreInfoService\Types\ImageType;
use App\Service\MoreInfoService\Types\MoreInfoResponse;
use App\Service\MoreInfoService\Types\RequestStatusType;
use App\Service\MoreInfoService\Utils\NoHitItem;
use Elastica\Query;
use Elastica\Type;
use Psr\Log\LoggerInterface;
use ReflectionException;
use SoapClient;
use SoapFault;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Service\MoreInfoService\Types\MoreInfoRequest;
use App\Service\MoreInfoService\Types\FormatType;
use App\Service\MoreInfoService\Types\AuthenticationType;

/**
 * moreInfoService class.
 *
 * @author wsdl2php
 */
abstract class AbstractMoreInfoService extends SoapClient
{
    /**
     * Default class mapping for this service.
     *
     * @var array
     */
    private static $classMap = [
        'AuthenticationType' => AuthenticationType::class,
        'FormatType' => FormatType::class,
        'IdentifierInformationType' => IdentifierInformationType::class,
        'IdentifierType' => IdentifierType::class,
        'ImageType' => ImageType::class,
        'RequestStatusType' => RequestStatusType::class,
        'MoreInfoRequest' => MoreInfoRequest::class,
        'MoreInfoResponse' => MoreInfoResponse::class,
    ];

    private $namespace;
    private $index;
    private $statsLogger;
    private $requestStack;
    private $dispatcher;
    private $transformer;

    /**
     * MoreInfoService constructor.
     *
     * @param Type $index
     *   Elastica index
     * @param LoggerInterface $statsLogger
     *   Statistics logger
     * @param RequestStack $requestStack
     *   HTTP RequestStack
     * @param eventDispatcherInterface $dispatcher
     *   Dispatch events
     * @param coverStoreTransformationInterface $transformer
     *   URL transformation service
     * @param array $options
     *   Any additional parameters to add to the service
     *
     * @throws SoapFault
     */
    public function __construct(Type $index, LoggerInterface $statsLogger, RequestStack $requestStack,
                                EventDispatcherInterface $dispatcher, CoverStoreTransformationInterface $transformer,
                                array $options = [])
    {
        $this->index = $index;
        $this->statsLogger = $statsLogger;
        $this->requestStack = $requestStack;
        $this->dispatcher = $dispatcher;
        $this->transformer = $transformer;

        // Add the classmap to the options.
        foreach (self::$classMap as $serviceClassName => $mappedClassName) {
            if (!isset($options['classmap'][$serviceClassName])) {
                $options['classmap'][$serviceClassName] = $mappedClassName;
            }
        }

        parent::__construct($this->getWsdl(), $options);
    }

    abstract protected function getNameSpace(): string;

    abstract protected function getWsdl(): string;

    /**
     * Service call proxy.
     *
     * @param string $serviceName
     *   The name of the service being called
     * @param array $parameters
     *   The parameters being supplied to the service
     * @param \SoapHeader[] $requestHeaders
     *   An array of SOAPHeaders
     *
     * @return mixed the service response
     */
    protected function callProxy(string $serviceName, array $parameters = null, array $requestHeaders = null)
    {
        $result = $this->__soapCall(
            $serviceName,
            $parameters,
            [
                'uri' => '',
                'soapaction' => '',
            ],
            !empty($requestHeaders) ? array_filter($requestHeaders) : null,
            $responseHeaders
        );

        if (!empty($responseHeaders)) {
            foreach ($responseHeaders as $headerName => $headerData) {
                $this->$headerName = $headerData;
            }
        }

        return $result;
    }

    /**
     * Build and populate a SOAP header.
     *
     * @param string $headerName
     *   The name of the services SOAP Header
     * @param array|object $rawHeaderData
     *   Any data that can be mapped to the SOAP Header. Public properties of objects will be used if an object is supplied.
     * @param string $namespace
     *   The namespace which will default to this service's namespace
     *
     * @throws ReflectionException
     */
    public function assignSoapHeader(string $headerName, $rawHeaderData = null, string $namespace): void
    {
        if (!$namespace) {
            $namespace = $this->getNameSpace();
        }

        // Is there a corresponding property of this service for the requested SOAP Header?
        // Is there a mapped class for this SOAP Header?
        // Do we have any data to populate the SOAP Header with?
        if (property_exists($this, $headerName) && isset(self::$classMap[$headerName]) && !empty($rawHeaderData)) {
            // Start with no data for the SOAP Header.
            $dataForSoapHeader = [];
            $mappedData = [];

            // Get the mapped class and get the properties defined for the SOAP Header.
            $reflectedHeader = new \ReflectionClass(self::$classMap[$headerName]);
            $reflectedHeaderProperties = $reflectedHeader->getProperties();

            // Produce an array of public data from an object.
            if (\is_object($rawHeaderData)) {
                $reflectedData = new \ReflectionClass($rawHeaderData);
                $reflectedDataProperties = $reflectedData->getProperties(\ReflectionProperty::IS_PUBLIC);
                $mappedData = [];
                foreach ($reflectedDataProperties as $property) {
                    $propertyName = $property->name;
                    $mappedData[$propertyName] = $rawHeaderData->$propertyName;
                }
            } elseif (\is_array($rawHeaderData)) {
                $mappedData = $rawHeaderData;
            }

            // Process the data as an array.
            if (!empty($mappedData)) {
                foreach ($reflectedHeaderProperties as $property) {
                    $propertyName = $property->name;
                    if (isset($mappedData[$propertyName])) {
                        $dataForSoapHeader[$propertyName] = $mappedData[$propertyName];
                    }
                }
            }

            // Build the SOAP Header and assign it the corresponding property.
            $this->$headerName = new \SoapHeader($namespace, $headerName, $dataForSoapHeader);
        }
    }

    /**
     * Validate and respond to a 'moreInfo' SOAP request.
     *
     * @param $body
     *
     * @return MoreInfoResponse
     *
     * @throws MoreInfoException
     * @throws CoverStoreTransformationException
     */
    public function moreInfo($body): MoreInfoResponse
    {
        $this->validateRequestAuthentication($body);

        $searchParameters = $this->getSearchParameters($body);

        // Build Elastic Query Results
        $boolQuery = $this->buildElasticQuery($searchParameters);
        $search = $this->index->search($boolQuery);
        $results = $search->getResults();

        $this->statsLogger->info('Cover request/response', [
            'service' => 'MoreInfoService',
            'clientID' => $body->authentication->authenticationGroup,
            'remoteIP' => $this->requestStack->getCurrentRequest()->getClientIp(),
            'searchParameters' => $searchParameters,
            'fileNames' => $this->getImageUrls($results),
        ]);

        $response = $this->buildSoapResponse($searchParameters, $results);

        $this->registerSearchNoHits($response->identifierInformation);

        return $response;
    }

    /**
     * Send event to register identifiers that gave no search results.
     *
     * @param array $identifierInformation
     */
    private function registerSearchNoHits(array $identifierInformation): void
    {
        $noHits = [];

        foreach ($identifierInformation as $info) {
            if (!$info->identifierKnown) {
                foreach ($info->identifier as $isType => $isIdentifier) {
                    if (!empty($isIdentifier)) {
                        $noHits[] = new NoHitItem($isType, $isIdentifier);
                    }
                }
            }
        }

        if ($noHits) {
            $event = new SearchNoHitEvent($noHits);
            $this->dispatcher->dispatch($event::NAME, $event);
        }
    }

    /**
     * Validate SOAP request body authentication part.
     *
     * @param stdClass $body
     *
     * @throws MoreInfoException
     */
    private function validateRequestAuthentication(stdClass $body): void
    {
        // Check if authentication is set for usage logging.
        if (!property_exists($body->authentication, 'authenticationGroup')
            || empty($body->authentication->authenticationGroup)) {
            throw new MoreInfoException('authenticationGroup missing');
        }
    }

    /**
     * Get an array of search parameters from request body.
     *
     * @param stdClass $body
     *
     * @return array
     *
     * @throws MoreInfoException
     */
    public function getSearchParameters(stdClass $body): array
    {
        // Check for identifier on body
        if (!property_exists($body, 'identifier')) {
            throw new MoreInfoException('Request identifier missing');
        }

        // 'identifier' can be an array of stdClass objects or one single stdClass object.
        $identifiers = \is_array($body->identifier) ? $body->identifier : [$body->identifier];

        $searchParameters = [];

        foreach ($identifiers as $identifier) {
            if ('object' !== \gettype($identifier) && 'stdClass' !== \get_class($identifier)) {
                throw new MoreInfoException('Request identifier unknown type');
            }

            $identifierArray = get_object_vars($identifier);
            if (empty($identifierArray)) {
                throw new MoreInfoException('Request identifier unknown type');
            }

            foreach ($identifierArray as $isType => $isIdentifier) {
                if ('string' !== \gettype($isIdentifier)) {
                    throw new MoreInfoException('To many request identifiers');
                }

                if ('pidList' === $isType) {
                    $isType = 'pid';
                    $isIdentifiers = explode('|', $isIdentifier);
                } else {
                    $isIdentifiers = [$isIdentifier];
                }

                if (!array_key_exists($isType, $searchParameters)) {
                    $searchParameters[$isType] = [];
                }

                $this->mergeByReference($searchParameters[$isType], $isIdentifiers);
            }
        }

        return $searchParameters;
    }

    /**
     * Merge from one array into another by reference.
     *
     * PHPs array_merge() performance is not always optimal:
     * https://stackoverflow.com/questions/23348339/optimizing-array-merge-operation
     *
     * @param array $mergeTo
     * @param array $mergeFrom
     */
    private function mergeByReference(array &$mergeTo, array &$mergeFrom): void
    {
        foreach ($mergeFrom as $i) {
            $mergeTo[] = $i;
        }
    }

    /**
     * Build Elastic query from IS type and IS identifiers.
     *
     * @param array $searchParameters
     *
     * @return Query\BoolQuery
     */
    private function buildElasticQuery(array $searchParameters): Query\BoolQuery
    {
        $masterQuery = new Query\BoolQuery();

        foreach ($searchParameters as $isType => $isIdentifiers) {
            $subQuery = new Query\BoolQuery();

            $identifierFieldTermsQuery = new Query\Terms();
            $identifierFieldTermsQuery->setTerms('isIdentifier', $isIdentifiers);
            $subQuery->addMust($identifierFieldTermsQuery);

            $typeFieldTermQuery = new Query\Term();
            $typeFieldTermQuery->setTerm('isType', $isType);
            $subQuery->addMust($typeFieldTermQuery);

            $masterQuery->addShould($subQuery);
        }

        return $masterQuery;
    }

    /**
     * Build SOAP response object for 'moreInfo' request.
     *
     * @param array $searchParameters
     * @param array $results
     *
     * @return MoreInfoResponse
     *
     * @throws CoverStoreTransformationException
     */
    private function buildSoapResponse(array $searchParameters, array $results): MoreInfoResponse
    {
        $requestStatus = new RequestStatusType();
        $requestStatus->statusEnum = 'ok';
        $requestStatus->errorText = '';

        $identifierInformationList = [];
        foreach ($searchParameters as $isType => $isIdentifiers) {
            foreach ($isIdentifiers as $isIdentifier) {
                $identifierInformation = new IdentifierInformationType();
                $identifierInformation->identifierKnown = false;

                $identifier = new IdentifierType();
                $identifier->{$isType} = $isIdentifier;

                $identifierInformation->identifier = $identifier;

                $identifierInformationList[$isIdentifier] = $identifierInformation;
            }
        }

        foreach ($results as $result) {
            $data = $result->getData();

            $identifierInformation = $identifierInformationList[$data['isIdentifier']];
            $identifierInformation->identifierKnown = true;

            $image = new ImageType();
            $image->_ = $this->transformer->transform($data['imageUrl']);
            // @TODO add imageFormat to search/elastic so we can expose it here.
            $image->imageSize = 'detail';
            $image->imageFormat = $data['imageFormat'];

            $identifierInformation->coverImage[] = $image;
        }

        $response = new MoreInfoResponse();
        $response->requestStatus = $requestStatus;
        $response->identifierInformation = array_values($identifierInformationList);

        return $response;
    }

    /**
     * Get image URLs from search result.
     *
     * @param array $results
     *
     * @return mixed
     */
    private function getImageUrls(array $results)
    {
        $urls = [];
        foreach ($results as $result) {
            $data = $result->getData();
            $urls[] = $data['imageUrl'];
        }

        return empty($urls) ? null : $urls;
    }
}
