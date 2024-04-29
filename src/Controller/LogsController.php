<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use App\Entity\Logs;

use App\Repository\LogsRepository;

class LogsController extends AbstractController
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

    #[Route('/logs', name: 'app_logs')]
    public function index(): Response
    {
        if (!$this->getUser() || !$this->isGranted('IS_AUTHENTICATED')) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $pages = array("page" => 1,"next" => false,"prev" => false);

        if(isset($_GET["page"]) && $_GET["page"] > 0) {
            $pages["page"] = intval($_GET["page"]);
        } else {
            $pages["page"] = 1;
        }

        if(isset($_GET["perpage"]) && $_GET["perpage"] > 0) {
            $pages["perpage"] = intval($_GET["perpage"]);
        } else {
            $pages["perpage"] = 17;
        }

        $repository = $this->em->getRepository(Logs::class);
        $logs = $repository->findBy(array(), array('id' => 'DESC'), $pages["perpage"], ($pages["page"] - 1) * $pages["perpage"]);
        $totallogs = $repository->getTotalRows();

        if(count($logs) == 0 && $totallogs != 0) {
            return $this->redirectToRoute('app_logs');
        }

        if($totallogs / $pages["perpage"] > $pages["page"]) {
            $pages["next"] = true;
        }
        if($pages["page"] - 1 > 0) {
            $pages["prev"] = true;
        }

        return $this->render($this->getParameter('app.theme').'/logs/index.html.twig', [
            'logs' => $logs,
            'pages' => $pages,
            'menuactive' => 'logs',
            'breadcrumbs' => array(array('name' => $this->translator->trans("Logs"), 'url' => $this->router->generate('app_logs'))),
        ]);
    }
}
