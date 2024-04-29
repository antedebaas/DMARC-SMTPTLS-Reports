<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use App\Entity\Users;
use App\Entity\Domains;
use App\Entity\DMARC_Reports;
use App\Entity\SMTPTLS_Reports;
use App\Entity\Logs;

class DashboardController extends AbstractController
{
    private $em;
    private $router;
    private $translator;

    public function __construct(EntityManagerInterface $em, UrlGeneratorInterface $router, TranslatorInterface $translator)
    {
        $this->em = $em;
        $this->router = $router;
        $this->translator = $translator;
    }

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        if (!file_exists(dirname(__FILE__).'/../../.env.local')) {
            return $this->redirectToRoute('app_setup');
        }

        $userRepository = $this->em->getRepository(Users::class);
        if($userRepository->count([]) == 0) {
            return $this->redirectToRoute('app_setup');
        }

        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $repository = $this->em->getRepository(Domains::class);
        $domains = $repository->findBy(array('id' => $userRepository->findDomains($this->getUser())));

        $repository = $this->em->getRepository(DMARC_Reports::class);
        if(in_array("ROLE_ADMIN", $this->getUser()->getRoles())) {
            $dmarcreports = $repository->findBy(array(), array('id' => 'DESC'), 5, 0);
        } else {
            $dmarcreports = $repository->findBy(array('domain' => $domains), array('id' => 'DESC'), 5, 0);
        }
        $totalreports = $repository->getTotalRows($domains);

        $repository = $this->em->getRepository(SMTPTLS_Reports::class);
        if(in_array("ROLE_ADMIN", $this->getUser()->getRoles())) {
            $smtptlsreports = $repository->findBy(array(), array('id' => 'DESC'), 5, 0);
        } else {
            $smtptlsreports = $repository->findOwnedBy($domains, array('id' => 'DESC'), 5, 0);
        }
        $totalreports = $repository->getTotalRows($domains);

        $repository = $this->em->getRepository(Logs::class);
        $logs = $repository->findBy(array(), array('id' => 'DESC'), 3, 0);

        return $this->render($this->getParameter('app.theme').'/dashboard/index.html.twig', [
            'menuactive' => 'dashboard',
            'breadcrumbs' => array(array('name' => $this->translator->trans("Dashboard"), 'url' => $this->router->generate('app_dashboard'))),
            'dmarcreports' => $dmarcreports,
            'smtptlsreports' => $smtptlsreports,
            'logs' => $logs,
        ]);
    }
}
