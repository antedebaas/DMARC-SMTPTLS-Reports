<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use App\Form\RegistrationFormType;
use App\Form\CreateEnvType;

use App\Entity\Users;
use App\EntityUnmanaged\DoctrineMigrationVersions;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

use Symfony\Component\Finder\Finder;

class SetupController extends AbstractController
{
    private $em;
    private $params;

    public function __construct(EntityManagerInterface $em, ParameterBagInterface $params)
    {
        $this->em = $em;
        $this->params = $params;
    }

    #[Route('/setup', name: 'app_setup')]
    public function index(Request $request, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        $setup['envfile'] = false;
        $setup['database'] = false;
        $setup['missingmigrations'] = false;
        $setup['users'] = false;
        $setup['all'] = false;

        $session = $request->getSession();
        $session->set('schema-updatable', false);

        // check if .env.local file exists indicating that the setup has been run
        $setup['envfile'] = file_exists(dirname(__FILE__).'/../../.env.local');
        if($setup['envfile'] == false) {
            $form = $this->createForm(CreateEnvType::class);

            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $formdata = $form->getData();
                $formdata['randomstring'] = $this->generateRandomString();

                $envtemplate = $this->render($this->getParameter('app.theme').'/setup/env.txt.twig', [
                    'f' => $formdata,
                ]);
                $envfile = __DIR__."/../../.env.local";
                file_put_contents($envfile, $envtemplate->getContent());

                $this->clearCacheAction();

                return $this->redirectToRoute('app_setup');
            }
            $setup['users_form'] = $form->createView();
        } else {
            try {
                $doctrinemigrations = $this->em->createQueryBuilder()
                ->select('m.version')
                ->from(DoctrineMigrationVersions::class, 'm')
                ->orderBy('m.version', 'ASC')
                ->getQuery()
                ->getResult();
                $migrations = array();
                foreach ($doctrinemigrations as $migration) {
                    $migrations[] = $migration['version'];
                }
            } catch (\Exception $e) {
                $migrations = array();
            }


            $migrationfiles = array();
            $finder = new Finder();
            $finder->files()->in(__DIR__.'/../../migrations/'.$this->params->get('app.database_type'));
            foreach ($finder as $file) {
                $migrationfiles[] = "DoctrineMigrations\\".str_replace(".php", "", $file->getBasename());
            }

            //if there are migrations in the filesystem that are not in the database this will show them
            $migrationdifferences_todo = array_diff($migrationfiles, $migrations);
            if(count($migrationdifferences_todo) == 0) {
                $setup['database'] = true;
            } else {
                $setup['database'] = false;
                $setup['database_list'] = $migrationdifferences_todo;
                $session->set('schema-updatable', true);
            }

            //if there are migrations in the database that are not in the filesystem this will show them
            $migrationdifferences_missingfiles = array_diff($migrations, $migrationfiles);
            if(count($migrationdifferences_missingfiles) == 0) {
                $setup['missingmigrations'] = true;
            } else {
                $setup['missingmigrations'] = false;
                $setup['missingmigrations_list'] = $migrationdifferences_missingfiles;
            }

            try {
                // check if there are users in the database
                $number_of_users = $this->em->getRepository(Users::class)->getTotalRows();
                if($number_of_users > 0) {
                    $setup['users'] = true;
                } else {
                    $setup['users'] = false;
                    $form = $this->createForm(RegistrationFormType::class);

                    $form->handleRequest($request);
                    if ($form->isSubmitted() && $form->isValid()) {
                        $formdata = $form->getData();
                        $formdata->setPassword(
                            $userPasswordHasher->hashPassword(
                                $formdata,
                                $form->get('plainPassword')->getData()
                            )
                        );
                        $formdata->SetRoles(array('ROLE_ADMIN'));
                        $formdata->setIsVerified(true);
                        $this->em->persist($formdata);
                        $this->em->flush();

                        return $this->redirectToRoute('app_setup');
                    }
                    $setup['users_form'] = $form->createView();
                }
            } catch (\Exception $e) {
                $setup['users'] = false;
            }
        }

        if($setup['envfile'] && $setup['database'] && $setup['missingmigrations'] && $setup['users']) {
            $setup['all'] = true;
        }

        return $this->render($this->getParameter('app.theme').'/setup/index.html.twig', [
            'setup' => $setup,
        ]);
    }

    #[Route('/setup/migrate', name: 'app_setup_migrate')]
    public function migrate(Request $request): Response
    {
        $session = $request->getSession();
        if($session->get('schema-updatable')) {
            // this will update the database to the latest version
            $this->updateAction();
        }

        $session->set('schema-updatable', false);
        return $this->redirectToRoute('app_setup');
    }

    private function updateAction()
    {
        $kernel = new Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput(array(
           'command' => 'doctrine:migrations:migrate',
           '--no-interaction',
        ));

        $output = new BufferedOutput();
        $application->run($input, $output);

        $output->fetch();
    }

    private function clearCacheAction()
    {
        $kernel = new Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput(array(
           'command' => 'cache:clear',
           '--no-interaction',
        ));

        $output = new BufferedOutput();
        $application->run($input, $output);

        $output->fetch();
    }

    private function generateRandomString($length = 32)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
