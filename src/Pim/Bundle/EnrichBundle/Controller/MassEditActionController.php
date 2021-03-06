<?php

namespace Pim\Bundle\EnrichBundle\Controller;

use Akeneo\Bundle\BatchBundle\Connector\ConnectorRegistry;
use Akeneo\Bundle\BatchBundle\Launcher\JobLauncherInterface;
use Akeneo\Component\Batch\Job\JobRepositoryInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionParametersParser;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Pim\Bundle\DataGridBundle\Adapter\GridFilterAdapterInterface;
use Pim\Bundle\EnrichBundle\AbstractController\AbstractDoctrineController;
use Pim\Bundle\EnrichBundle\MassEditAction\MassEditFormResolver;
use Pim\Bundle\EnrichBundle\MassEditAction\Operation\OperationRegistryInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Mass edit operation controller
 *
 * @author    Gildas Quemener <gildas@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MassEditActionController extends AbstractDoctrineController
{
    /** @var MassActionParametersParser */
    protected $parametersParser;

    /** @var ValidatorInterface */
    protected $validator;

    /** @var GridFilterAdapterInterface */
    protected $gridFilterAdapter;

    /** @var JobLauncherInterface */
    protected $simpleJobLauncher;

    /** @var JobRepositoryInterface */
    protected $jobRepository;

    /** @var ConnectorRegistry */
    protected $connectorRegistry;

    /** @var MassEditFormResolver */
    protected $massEditFormResolver;

    /** @var array */
    protected $gridNameRouteMapping;

    /**
     * @param Request                    $request
     * @param EngineInterface            $templating
     * @param RouterInterface            $router
     * @param TokenStorageInterface      $tokenStorage
     * @param FormFactoryInterface       $formFactory
     * @param ValidatorInterface         $validator
     * @param TranslatorInterface        $translator
     * @param EventDispatcherInterface   $eventDispatcher
     * @param ManagerRegistry            $doctrine
     * @param MassActionParametersParser $parametersParser
     * @param GridFilterAdapterInterface $gridFilterAdapter
     * @param JobLauncherInterface       $simpleJobLauncher
     * @param JobRepositoryInterface     $jobRepository
     * @param ConnectorRegistry          $connectorRegistry
     * @param OperationRegistryInterface $operationRegistry
     * @param MassEditFormResolver       $massEditFormResolver
     * @param array                      $gridNameRouteMapping
     */
    public function __construct(
        Request $request,
        EngineInterface $templating,
        RouterInterface $router,
        TokenStorageInterface $tokenStorage,
        FormFactoryInterface $formFactory,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        EventDispatcherInterface $eventDispatcher,
        ManagerRegistry $doctrine,
        MassActionParametersParser $parametersParser,
        GridFilterAdapterInterface $gridFilterAdapter,
        JobLauncherInterface $simpleJobLauncher,
        JobRepositoryInterface $jobRepository,
        ConnectorRegistry $connectorRegistry,
        OperationRegistryInterface $operationRegistry,
        MassEditFormResolver $massEditFormResolver,
        array $gridNameRouteMapping = [
            'family-grid' => 'pim_enrich_family_index',
            'default'     => 'pim_enrich_product_index'
        ]
    ) {
        parent::__construct(
            $request,
            $templating,
            $router,
            $tokenStorage,
            $formFactory,
            $validator,
            $translator,
            $eventDispatcher,
            $doctrine
        );

        $this->parametersParser     = $parametersParser;
        $this->gridFilterAdapter    = $gridFilterAdapter;
        $this->simpleJobLauncher    = $simpleJobLauncher;
        $this->jobRepository        = $jobRepository;
        $this->connectorRegistry    = $connectorRegistry;
        $this->operationRegistry    = $operationRegistry;
        $this->massEditFormResolver = $massEditFormResolver;
        $this->gridNameRouteMapping = $gridNameRouteMapping;
    }

    /**
     * @Template
     * @AclAncestor("pim_enrich_mass_edit")
     *
     * @return array
     */
    public function chooseAction()
    {
        $gridName     = $this->request->get('gridName');
        $objectsCount = $this->request->get('objectsCount');
        $itemsName    = $this->getItemName($gridName);

        $form = $this->massEditFormResolver->getAvailableOperationsForm($gridName);

        if ($this->request->isMethod('POST')) {
            $form->submit($this->request);
            if ($form->isValid()) {
                $data = $form->getData();

                return $this->redirectToRoute(
                    'pim_enrich_mass_edit_action_configure',
                    $this->getQueryParams() + ['operationAlias' => $data['operationAlias']]
                );
            }
        }

        return [
            'form'        => $form->createView(),
            'count'       => $objectsCount,
            'queryParams' => $this->getQueryParams(),
            'itemsName'   => $itemsName
        ];
    }

    /**
     * @param string $operationAlias
     *
     * @AclAncestor("pim_enrich_mass_edit")
     *
     * @throws NotFoundHttpException
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function configureAction($operationAlias)
    {
        $operation    = $this->operationRegistry->get($operationAlias);
        $itemsName    = $operation->getItemsName();
        $productCount = $this->request->get('objectsCount');

        $form = $this->massEditFormResolver->getConfigurationForm($operationAlias);

        if ($this->request->isMethod('POST')) {
            $form->remove('operationAlias');
            $form->submit($this->request);
            $form = $this->massEditFormResolver->getConfigurationForm($operationAlias, $form->getNormData());
            $operation = $form->getNormData();
        }

        return $this->render(
            sprintf('PimEnrichBundle:MassEditAction:configure/%s.html.twig', $operationAlias),
            [
                'form'           => $form->createView(),
                'operationAlias' => $operationAlias,
                'operation'      => $operation,
                'queryParams'    => $this->getQueryParams(),
                'productCount'   => $productCount,
                'itemsName'      => $itemsName,
            ]
        );
    }

    /**
     * @param string $operationAlias
     *
     * @AclAncestor("pim_enrich_mass_edit")
     *
     * @throws NotFoundResourceException
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function performAction($operationAlias)
    {
        $gridName     = $this->request->get('gridName');
        $operation    = $this->operationRegistry->get($operationAlias);
        $itemsName    = $operation->getItemsName();
        $productCount = $this->request->get('objectsCount');

        $form = $this->massEditFormResolver->getConfigurationForm($operationAlias);
        $form->remove('operationAlias');
        $form->submit($this->request);

        if ($form->isValid()) {
            $operation = $form->getData();
            $pimFilters = $this->gridFilterAdapter->adapt($this->request);
            $operation->setFilters($pimFilters);

            $jobCode = $operation->getBatchJobCode();
            $jobInstance = $this->getRepository('Akeneo\Component\Batch\Model\JobInstance')
                ->findOneBy(['code' => $jobCode]);

            if (null === $jobInstance) {
                throw new NotFoundResourceException(sprintf('No job found with job code "%s"', $jobCode));
            }

            $operation->finalize();

            $rawConfiguration = $operation->getBatchConfig();
            $this->simpleJobLauncher->launch($jobInstance, $this->getUser(), $rawConfiguration);
        }

        if ($form->isValid()) {
            $this->addFlash(
                'success',
                sprintf('pim_enrich.mass_edit_action.%s.launched_flash', $operationAlias)
            );

            $route = $this->getRouteFromMapping($gridName);

            return $this->redirectToRoute($route);
        }

        return $this->render(
            sprintf('PimEnrichBundle:MassEditAction:configure/%s.html.twig', $operationAlias),
            [
                'form'           => $form->createView(),
                'operationAlias' => $operationAlias,
                'itemsName'      => $itemsName,
                'productCount'   => $productCount,
                'queryParams'    => $this->getQueryParams()
            ]
        );
    }

    /**
     * @param string $gridName
     *
     * @return string
     */
    protected function getItemName($gridName)
    {
        $gridPattern = '-grid';
        if (false === strpos($gridName, $gridPattern)) {
            $itemsName = 'item';
        } else {
            $itemsName = str_replace($gridPattern, '', $gridName);
            $itemsName = str_replace('-', '_', $itemsName);
        }

        return $itemsName;
    }

    /**
     * Get the datagrid query parameters
     *
     * @return array
     */
    protected function getQueryParams()
    {
        $params = $this->parametersParser->parse($this->request);

        $params['gridName']     = $this->request->get('gridName');
        $params['actionName']   = $this->request->get('actionName');
        $params['values']       = implode(',', $params['values']);
        $params['filters']      = json_encode($params['filters']);
        $params['dataLocale']   = $this->request->get('dataLocale', null);
        $params['objectsCount'] = $this->request->get('objectsCount');

        return $params;
    }

    /**
     * Return the route to follow after a performed action
     *
     * @param string $gridName
     *
     * @return string
     */
    protected function getRouteFromMapping($gridName)
    {
        if (isset($this->gridNameRouteMapping[$gridName])) {
            return $this->gridNameRouteMapping[$gridName];
        }

        return $this->gridNameRouteMapping['default'];
    }
}
