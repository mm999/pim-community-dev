<?php

namespace Oro\Bundle\InstallerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\ProcessBuilder;

class InstallCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('oro:install')
            ->setDescription('Oro Application Installer.')
            ->addOption('user-name', null, InputOption::VALUE_OPTIONAL, 'User name')
            ->addOption('user-email', null, InputOption::VALUE_OPTIONAL, 'User email')
            ->addOption('user-firstname', null, InputOption::VALUE_OPTIONAL, 'User first name')
            ->addOption('user-lastname', null, InputOption::VALUE_OPTIONAL, 'User last name')
            ->addOption('user-password', null, InputOption::VALUE_OPTIONAL, 'User password')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force installation')
            ->addOption(
                'sample-data',
                null,
                InputOption::VALUE_OPTIONAL,
                'Determines whether sample data need to be loaded or not'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $forceInstall = $input->getOption('force');

        // if there is application is not installed or no --force option
        if ($this->getContainer()->hasParameter('installed') && $this->getContainer()->getParameter('installed')
            && !$forceInstall
        ) {
            throw new \RuntimeException('Oro Application already installed.');
        } elseif ($forceInstall) {
            // if --force option we have to clear cache
            $this->clearCache($input, $output);
        }

        $output->writeln('<info>Installing Oro Application.</info>');
        $output->writeln('');

        $this
            ->checkStep($input, $output)
            ->databaseStep($input, $output)
            ->assetsStep($input, $output)
            ->updateInstalledFlag($input, $output);

        $output->writeln('');
        $output->writeln('<info>Oro Application has been successfully installed.</info>');
    }

    /**
     * Step where configuration is checked
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \RuntimeException
     *
     * @return \Oro\Bundle\InstallerBundle\Command\InstallCommand
     */
    protected function checkStep(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Oro requirements check:</info>');

        if (!class_exists('OroRequirements')) {
            require_once $this->getContainer()->getParameter('kernel.root_dir')
                . DIRECTORY_SEPARATOR
                . 'OroRequirements.php';
        }

        $collection = new \OroRequirements();

        $this->renderTable($collection->getMandatoryRequirements(), 'Mandatory requirements', $output);
        $this->renderTable($collection->getPhpIniRequirements(), 'PHP settings', $output);
        $this->renderTable($collection->getOroRequirements(), 'Oro specific requirements', $output);
        $this->renderTable($collection->getRecommendations(), 'Optional recommendations', $output);

        if (count($collection->getFailedRequirements())) {
            throw new \RuntimeException(
                'Some system requirements are not fulfilled. Please check output messages and fix them.'
            );
        }

        $output->writeln('');

        return $this;
    }

    /**
     * Step where the database is built, the fixtures loaded and some command scripts launched
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return InstallCommand
     */
    protected function databaseStep(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Prepare database schema</info>');

        $this
            ->runCommand('doctrine:schema:drop', $input, $output, array('--force' => true, '--full-database' => true))
            ->runCommand('doctrine:schema:create', $input, $output)
            ->runCommand('oro:entity-config:init', $input, $output)
            ->runCommand('oro:entity-extend:init', $input, $output)
            ->runCommand(
                'oro:entity-extend:update-config',
                $input,
                $output,
                array('--process-isolation' => true)
            )
            ->runCommand(
                'doctrine:schema:update',
                $input,
                $output,
                array('--process-isolation' => true, '--force' => true, '--no-interaction' => true)
            )
            ->loadFixturesStep($input, $output)
            ->launchCommands($input, $output);

        return $this;
    }

    /**
     * Step where fixtures are loaded
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return InstallCommand
     */
    protected function loadFixturesStep(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Load fixtures step.</info>');

        $this
            ->loadFixtures($input, $output)
            ->setUp($input, $output)
            ->loadSampleData($input, $output);

        $output->writeln('');

        return $this;
    }

    /**
     * Load default data fixtures
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return InstallCommand
     */
    protected function loadFixtures(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Load fixtures.</info>');

        $this->runCommand(
            'doctrine:fixtures:load',
            $input,
            $output,
            array('--process-isolation' => true, '--no-interaction' => true, '--append' => true)
        );

        $output->writeln('');

        return $this;
    }

    /**
     * Extension point to override if installation interactivity is needed
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return InstallCommand
     */
    protected function setUp(InputInterface $input, OutputInterface $output)
    {
        $this->userSetup($input, $output);

        return $this;
    }

    /**
     * Set up the user information
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return InstallCommand
     */
    protected function userSetup(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Administration setup.</info>');

        $user = $this->getContainer()->get('oro_user.manager')->createUser();
        $role = $this->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('OroUserBundle:Role')
            ->findOneBy(array('role' => 'ROLE_ADMINISTRATOR'));

        $businessUnit = $this->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('OroOrganizationBundle:BusinessUnit')
            ->findOneBy(array('name' => 'Main'));

        // @TODO: Must be a validator !!
        $passValidator = function ($value) {
            if (strlen(trim($value)) < 2) {
                throw new \Exception('The password must be at least 2 characters long');
            }

            return $value;
        };

        $dialog = $this->getHelperSet()->get('dialog');
        $options = $input->getOptions();
        $userName = isset($options['user-name'])
            ? $options['user-name']
            : $dialog->ask($output, '<question>Username:</question> ');
        $userEmail = isset($options['user-email'])
            ? $options['user-email']
            : $dialog->ask($output, '<question>Email:</question> ');
        $userFirstName = isset($options['user-firstname'])
            ? $options['user-firstname']
            : $dialog->ask($output, '<question>First name:</question> ');
        $userLastName = isset($options['user-lastname'])
            ? $options['user-lastname']
            : $dialog->ask($output, '<question>Last name:</question> ');
        $userPassword = isset($options['user-password'])
            ? $options['user-password']
            : $dialog->askHiddenResponseAndValidate($output, '<question>Password:</question> ', $passValidator);

        $user
            ->setUsername($userName)
            ->setEmail($userEmail)
            ->setFirstName($userFirstName)
            ->setLastName($userLastName)
            ->setPlainPassword($userPassword)
            ->setEnabled(true)
            ->addRole($role)
            ->setOwner($businessUnit)
            ->addBusinessUnit($businessUnit);

        $this->getContainer()->get('oro_user.manager')->updateUser($user);

        $output->writeln('');

        return $this;
    }

    /**
     * Load sample datas if needed
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return InstallCommand
     */
    protected function loadSampleData(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Load sample data.</info>');

        $demo = $input->hasOption('sample-data')
            ? strtolower($input->getOption('sample-data')) === 'y'
            : $dialog->askConfirmation($output, '<question>Load sample data (y/n)?</question> ', false);

        if ($demo) {
            $this->runCommand(
                'oro:demo:fixtures:load',
                $input,
                $output,
                array('--process-isolation' => true, '--process-timeout' => 300)
            );
        }

        $output->writeln('');

        return $this;
    }

    /**
     * Launchs all commands needed after fixtures loading
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return InstallCommand
     */
    protected function launchCommands(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Launch commands.</info>');

        $this->runCommand('oro:search:create-index', $input, $output);

        $output->writeln('');

        return $this;
    }

    /**
     * Load only assets
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return InstallCommand
     */
    protected function assetsStep(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Preparing application.</info>');

        $this
            ->runCommand('oro:navigation:init', $input, $output)
            ->runCommand('fos:js-routing:dump', $input, $output, array('--target' => 'web/js/routes.js'))
            ->runCommand('oro:localization:dump', $input, $output)
            ->runCommand('assets:install', $input, $output)
            ->runCommand('assetic:dump', $input, $output)
            ->runCommand('oro:assetic:dump', $input, $output)
            ->runCommand('oro:translation:dump', $input, $output)
            ->runCommand('oro:requirejs:build', $input, $output);
        ;

        $output->writeln('');

        return $this;
    }

    /**
     * Update installed flag
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return InstallCommand
     */
    protected function updateInstalledFlag(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Updating installed flag.</info>');

        $dumper = $this->getContainer()->get('oro_installer.yaml_persister');
        $params = $dumper->parse();
        $params['system']['installed'] = date('c');
        $dumper->dump($params);

        $this->clearCache($input, $output);
        $output->writeln('');

        return $this;
    }

    /**
     * Run clear cache command
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return InstallCommand
     */
    protected function clearCache(InputInterface $input, OutputInterface $output)
    {
        return $this->runCommand('cache:clear', $input, $output);
    }

    /**
     * Render requirements table
     *
     * @param array  $collection
     * @param string $header
     * @param OutputInterface $output
     */
    protected function renderTable(array $collection, $header, OutputInterface $output)
    {
        $table = $this->getHelperSet()->get('table');

        $table
            ->setHeaders(array('Check  ', $header))
            ->setRows(array());

        foreach ($collection as $requirement) {
            if ($requirement->isFulfilled()) {
                $table->addRow(array('OK', $requirement->getTestMessage()));
            } else {
                $table->addRow(
                    array(
                        $requirement->isOptional() ? 'WARNING' : 'ERROR',
                        $requirement->getHelpText()
                    )
                );
            }
        }

        $table->render($output);
    }

    /**
     * Launches a command.
     * If '--process-isolation' parameter is specified the command will be launched as a separate process.
     * In this case you can parameter '--process-timeout' to set the process timeout
     * in seconds. Default timeout is 60 seconds.
     *
     * @param string          $command
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param array           $params
     * @return InstallCommand
     */
    protected function runCommand($command, InputInterface $input, OutputInterface $output, $params = array())
    {
        $params = array_merge(
            array(
                'command'    => $command,
                '--no-debug' => true,
            ),
            $params
        );
        if ($input->hasOption('env') && $input->getOption('env') !== 'dev') {
            $params['--env'] = $input->getOption('env');
        }

        if (array_key_exists('--process-isolation', $params)) {
            unset($params['--process-isolation']);
            $phpFinder = new PhpExecutableFinder();
            $php = $phpFinder->find();
            $pb = new ProcessBuilder();
            $pb
                ->add($php)
                ->add($_SERVER['argv'][0]);

            if (array_key_exists('--process-timeout', $params)) {
                $pb->setTimeout($params['--process-timeout']);
                unset($params['--process-timeout']);
            }

            foreach ($params as $param => $val) {
                if ($param && '-' === $param[0]) {
                    if ($val === true) {
                        $pb->add($param);
                    } else {
                        $pb->add($param . '=' . $val);
                    }
                } else {
                    $pb->add($val);
                }
            }

            $process = $pb
                ->inheritEnvironmentVariables(true)
                ->getProcess();

            $process->run(
                function ($type, $data) use ($output) {
                    $output->write($data);
                }
            );
            $ret = $process->getExitCode();
        } else {
            $this->getApplication()->setAutoExit(false);
            $ret = $this->getApplication()->run(new ArrayInput($params), $output);
        }

        if (0 !== $ret) {
            $output->writeln(sprintf('<error>The command terminated with an error status (%s)</error>', $ret));
        }

        return $this;
    }
}
