<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use App\Entity\DMARC_Reports;
use App\Entity\SMTPTLS_Reports;
use App\Entity\Domains;
use App\Entity\Users;

class ReportsController extends AbstractController
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

    #[Route('/reports', name: 'app_reports')]
    public function index(): Response
    {
        if (!$this->getUser() || !$this->isGranted('IS_AUTHENTICATED')) {
            return $this->redirectToRoute('app_login');
        }

        $repository = $this->em->getRepository(Domains::class);
        $userRepository = $this->em->getRepository(Users::class);
        $domains = $repository->findBy(array('id' => $userRepository->findDomains($this->getUser())));

        $repository = $this->em->getRepository(DMARC_Reports::class);
        $dmarc_count = $repository->getTotalRows($domains);

        $repository = $this->em->getRepository(SMTPTLS_Reports::class);
        $smtptls_count = $repository->getTotalRows($domains);

        return $this->render($this->getParameter('app.theme').'/reports/index.html.twig', [
            'menuactive' => 'reports',
            'dmarc_count' => $dmarc_count,
            'smtptls_count' => $smtptls_count,
            'breadcrumbs' => array(array('name' => $this->translator->trans("Reports"), 'url' => $this->router->generate('app_reports'))),
        ]);
    }

    #[Route('/reports/checkmailnow', name: 'app_checkmailnow')]
    public function checkmailboxnow(): Response
    {
        if (!$this->getUser() || !$this->isGranted('IS_AUTHENTICATED')) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $kernel = new Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput(array(
            'command' => 'app:getreportsfrommailbox'
        ));

        $output = new BufferedOutput();
        $application->run($input, $output);

        return $this->redirectToRoute('app_dashboard');
    }
}
