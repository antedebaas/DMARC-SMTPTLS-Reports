<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use App\Form\UserFormType;

use App\Entity\Users;

use App\Repository\UsersRepository;

class UsersController extends AbstractController
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

    #[Route('/users', name: 'app_users')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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

        $repository = $this->em->getRepository(Users::class);
        $users = $repository->findBy(array(), array('id' => 'DESC'), $pages["perpage"], ($pages["page"] - 1) * $pages["perpage"]);
        $totalusers = $repository->getTotalRows();

        if(count($users) == 0 && $totalusers != 0) {
            return $this->redirectToRoute('app_logs');
        }

        if($totalusers / $pages["perpage"] > $pages["page"]) {
            $pages["next"] = true;
        }
        if($pages["page"] - 1 > 0) {
            $pages["prev"] = true;
        }

        return $this->render($this->getParameter('app.theme').'/users/index.html.twig', [
            'users' => $users,
            'pages' => $pages,
            'menuactive' => 'users',
            'breadcrumbs' => array(array('name' => $this->translator->trans("Users"), 'url' => $this->router->generate('app_users'))),
        ]);
    }

    #[Route('/user/add', name: 'app_user_add')]
    public function add(Request $request, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->getUser() || !$this->isGranted('IS_AUTHENTICATED')) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(UserFormType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $formdata = $form->getData();

            $password1 = $form->get("password1")->getData();
            $password2 = $form->get("password2")->getData();

            if($password1 == $password2 && $password1 != "") {
                $formdata->setPassword(
                    $userPasswordHasher->hashPassword(
                        $formdata,
                        $form->get('password1')->getData()
                    )
                );
            }

            $is_admin = $form->get("isAdmin")->getData();

            $domain_roles = $form->get("roles")->getData();
            $roles = array();
            foreach($domain_roles as $domain_role) {
                array_push($roles, $domain_role->getId());
            }
            if($is_admin == true) {
                array_push($roles, "ROLE_ADMIN");
            }
            $formdata->setRoles($roles);

            $this->em->persist($formdata);
            $this->em->flush();

            return $this->redirectToRoute('app_users');
        }
        $setup['users_form'] = $form->createView();

        return $this->render($this->getParameter('app.theme').'/users/edit.html.twig', [
            'menuactive' => 'users',
            'user' => null,
            'form' => $form,
            'breadcrumbs' => array(
                array('name' => $this->translator->trans("Users"), 'url' => $this->router->generate('app_users')),
                array('name' => "Add new user", 'url' => $this->router->generate('app_users'))
            ),
        ]);
    }

    #[Route('/user/edit/{id}', name: 'app_user_edit')]
    public function edit(Users $user, Request $request, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        if (!$this->getUser() || !$this->isGranted('IS_AUTHENTICATED')) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(UserFormType::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $formdata = $form->getData();

            $password1 = $form->get("password1")->getData();
            $password2 = $form->get("password2")->getData();

            if($password1 == $password2 && $password1 != "") {
                $formdata->setPassword(
                    $userPasswordHasher->hashPassword(
                        $formdata,
                        $form->get('password1')->getData()
                    )
                );
            }

            $is_admin = $form->get("isAdmin")->getData();

            $domain_roles = $form->get("roles")->getData();
            $roles = array();
            foreach($domain_roles as $domain_role) {
                array_push($roles, $domain_role->getId());
            }
            if($is_admin == true) {
                array_push($roles, "ROLE_ADMIN");
            }
            $formdata->setRoles($roles);

            $this->em->persist($formdata);
            $this->em->flush();

            return $this->redirectToRoute('app_users');
        }
        $setup['users_form'] = $form->createView();

        return $this->render($this->getParameter('app.theme').'/users/edit.html.twig', [
            'menuactive' => 'users',
            'user' => $user,
            'form' => $form,
            'breadcrumbs' => array(
                array('name' => $this->translator->trans("Users"), 'url' => $this->router->generate('app_users')),
                array('name' => $user->getEmail(), 'url' => $this->router->generate('app_users'))
            ),
        ]);
    }

    #[Route('/user/delete/{id}', name: 'app_user_delete')]
    public function delete(Users $user): Response
    {
        if (!$this->getUser() || !$this->isGranted('IS_AUTHENTICATED')) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $this->em->remove($user);
        $this->em->flush();

        return $this->redirectToRoute('app_users');
    }
}
