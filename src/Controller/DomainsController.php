<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use App\Form\DomainFormType;

use App\Entity\Users;
use App\Entity\Domains;
use App\Entity\MXRecords;

use App\Repository\DomainsRepository;

class DomainsController extends AbstractController
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

    #[Route('/domains', name: 'app_domains')]
    public function index(): Response
    {
        if (!$this->getUser() || !$this->isGranted('IS_AUTHENTICATED')) {
            return $this->redirectToRoute('app_login');
        }

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

        $repository = $this->em->getRepository(Domains::class);
        $usersRepository = $this->em->getRepository(Users::class);

        if(in_array("ROLE_ADMIN", $this->getUser()->getRoles())) {
            $domains = $repository->findAll(array(), array('fqdn' => 'DESC'), $pages["perpage"], ($pages["page"] - 1) * $pages["perpage"]);
        } else {
            $domains = $repository->findOwnedBy($usersRepository->findDomains($this->getUser()), array('fqdn' => 'DESC'), $pages["perpage"], ($pages["page"] - 1) * $pages["perpage"]);
        }

        $totaldomains = $repository->getTotalRows();

        if(count($domains) == 0 && $totaldomains != 0) {
            return $this->redirectToRoute('app_domains');
        }

        if($totaldomains / $pages["perpage"] > $pages["page"]) {
            $pages["next"] = true;
        }
        if($pages["page"] - 1 > 0) {
            $pages["prev"] = true;
        }

        return $this->render($this->getParameter('app.theme').'/domains/index.html.twig', [
            'domains' => $domains,
            'pages' => $pages,
            'menuactive' => 'domains',
            'breadcrumbs' => array(array('name' => $this->translator->trans("Domains"), 'url' => $this->router->generate('app_domains'))),
        ]);
    }

    #[Route('/domains/add', name: 'app_domains_add')]
    public function add(Request $request): Response
    {
        if (!$this->getUser() || !$this->isGranted('IS_AUTHENTICATED')) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(DomainFormType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $formdata = $form->getData();

            $this->em->persist($formdata);
            $this->em->flush();

            $usersRepository = $this->em->getRepository(Users::class);
            $user = $this->getUser();
            if(!$usersRepository->findIsAdmin($user->getId())) {
                $user->addDomain($formdata);
                $this->em->persist($user);
                $this->em->flush();
            }

            return $this->redirectToRoute('app_domains');
        }
        $setup['users_form'] = $form->createView();

        $dns_info = array(
            'now' => new \DateTime('now'),
            'ip' => $request->server->get('SERVER_ADDR'),
            'email' => $this->getParameter('app.mailbox_username'),
        );

        return $this->render($this->getParameter('app.theme').'/domains/edit.html.twig', [
            'menuactive' => 'domains',
            'domain' => null,
            'dns_info' => $dns_info,
            'form' => $form,
            'breadcrumbs' => array(
                array('name' => $this->translator->trans("Domains"), 'url' => $this->router->generate('app_domains')),
                array('name' => "Add new domain manually", 'url' => $this->router->generate('app_domains_add'))
            ),
        ]);
    }

    #[Route('/domains/edit/{id}', name: 'app_domains_edit')]
    public function edit(Domains $domain, Request $request): Response
    {
        if (!$this->getUser() || !$this->isGranted('IS_AUTHENTICATED')) {
            return $this->redirectToRoute('app_login');
        }

        $usersRepository = $this->em->getRepository(Users::class);
        if(!$usersRepository->denyAccessUnlessOwned(array($domain->getId()), $this->getUser())) {
            return $this->render($this->getParameter('app.theme').'/not_found.html.twig', []);
        }

        $form = $this->createForm(DomainFormType::class, $domain);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $formdata = $form->getData();

            $this->em->persist($formdata);
            $this->em->flush();

            return $this->redirectToRoute('app_domains');
        }
        $setup['users_form'] = $form->createView();

        $dns_info = array(
            'now' => new \DateTime('now'),
            'ip' => $request->server->get('SERVER_ADDR'),
            'email' => $this->getParameter('app.mailbox_username'),
        );

        return $this->render($this->getParameter('app.theme').'/domains/edit.html.twig', [
            'menuactive' => 'domains',
            'domain' => $domain,
            'dns_info' => $dns_info,
            'form' => $form,
            'breadcrumbs' => array(
                array('name' => $this->translator->trans("Domains"), 'url' => $this->router->generate('app_domains')),
                array('name' => $domain->getFqdn(), 'url' => $this->router->generate('app_domains'))
            ),
        ]);
    }

    #[Route('/domains/delete/{id}', name: 'app_domains_delete')]
    public function delete(Domains $domain): Response
    {
        if (!$this->getUser() || !$this->isGranted('IS_AUTHENTICATED')) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $this->em->remove($domain);
        $this->em->flush();

        return $this->redirectToRoute('app_domains');
    }
}
