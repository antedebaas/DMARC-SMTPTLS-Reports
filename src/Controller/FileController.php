<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Domains;

class FileController extends AbstractController
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/template/{name}.css', name: 'app_theme_stylesheet')]
    public function theme_stylesheet(string $name): Response
    {
        $response = $this->render($this->getParameter('app.theme').'/'.$name.'.css');
        $response->headers->set('Content-Type', 'text/css');
        return $response;
    }

    #[Route('/template/{name}.js', name: 'app_theme_javascript')]
    public function theme_javascript(string $name): Response
    {
        $response = $this->render($this->getParameter('app.theme').'/'.$name.'.js');
        $response->headers->set('Content-Type', 'text/javascript');
        return $response;
    }

    #[Route('/.well-known/mta-sts.txt', name: 'app_policy_file')]
    public function policyfile(Request $request, EntityManagerInterface $em): Response
    {
        preg_match('/(?:mta-sts\.)+(.*)/', $request->getHost(), $matches);
        $domainname = $matches[1];

        $repository = $this->em->getRepository(Domains::class);
        $domain = $repository->findOneBy(array('fqdn' => $domainname));

        foreach($domain->getMxRecords() as $mxrecord) {
            if($mxrecord->isInSTS()) {
                $mxnames[] = $mxrecord->getName();
            }
        }

        if($domain) {
            $response = $this->render($this->getParameter('app.theme').'/policy_file/mta-sts.txt.twig', [
                'version' => $domain->getStsVersion(),
                'mode' => $domain->getStsMode(),
                'max_age' => $domain->getStsMaxAge(),
                'mxnames' => $mxnames
            ]);
        } else {
            $response = $this->render($this->getParameter('app.theme').'/policy_file/empty.txt.twig');
        }
        $response->headers->set('Content-Type', 'text/plain');
        return $response;
    }

    #[Route('/autodiscover/autodiscover.xml', name: 'app_autodiscover_file')]
    public function autodiscoverfile(Request $request, EntityManagerInterface $em): Response
    {
        $domain = str_replace("autodiscover.", "", $request->getHost());
        $domain = str_replace("autoconfig.", "", $domain);
        $repository = $this->em->getRepository(Domains::class);
        $domain = $repository->findOneBy(array('fqdn' => $domain));
        if($domain) {
            preg_match("/\<EMailAddress\>(.*?)\<\/EMailAddress\>/", file_get_contents("php://input"), $matches);
            if(!array_key_exists('1', $matches)) {
                $matches[1] = "";
            }

            $response = $this->render($this->getParameter('app.theme').'/policy_file/autodiscover.xml.twig', [
                'loginname' => $matches[1],
                'mailsubdomain' => $domain->getMailhost(),
            ]);
        } else {
            $response = $this->render($this->getParameter('app.theme').'/policy_file/autodiscover.xml.twig', [
                'loginname' => "",
                'mailsubdomain' => ""
            ]);
        }


        $response->headers->set('Content-Type', 'application/xml');

        return $response;
    }
}
