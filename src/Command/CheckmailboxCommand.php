<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use Doctrine\ORM\EntityManagerInterface;
use SecIT\ImapBundle\Service\Imap;

use App\Entity\Domains;
use App\Entity\MXRecords;
use App\Entity\DMARC_Reports;
use App\Entity\DMARC_Records;
use App\Entity\DMARC_Results;
use App\Entity\SMTPTLS_Reports;
use App\Entity\SMTPTLS_Policies;
use App\Entity\SMTPTLS_MXRecords;
use App\Entity\SMTPTLS_FailureDetails;
use App\Entity\Logs;

#[AsCommand(
    name: 'app:checkmailbox',
    description: 'Checks the mailbox for new reports',
)]
class CheckmailboxCommand extends Command
{
    private $em;
    private $imap;

    public function __construct(EntityManagerInterface $em, Imap $imap)
    {
        $this->em = $em;
        $this->imap = $imap;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $stats=array(
            'new_emails' => 0,
            'new_domains' => 0,
            'new_mxrecords' => 0,
            'new_dmarc_reports' => 0,
            'new_dmarc_records' => 0,
            'new_dmarc_results' => 0,
            'new_smtptls_reports' => 0,
            'new_smtptls_policies' => 0,
            'new_smtptls_mxmapping' => 0,
            'new_smtptls_failuredetails' => 0
        );

        $mailresult = $this->open_mailbox($this->imap);
        $stats['new_emails'] = $mailresult['num_emails'];

        foreach($mailresult['reports']['dmarc_reports'] as $dmarcreport){
            $stats['new_dmarc_reports']++;

            $domain_repository = $this->em->getRepository(Domains::class);
            $dbdomain = $domain_repository->findOneBy(array('fqdn' => $dmarcreport->policy_published->domain->__toString()));
            if(!$dbdomain){
                $stats['new_domains']++;

                $dbdomain = new Domains;
                $dbdomain->setFqdn($dmarcreport->policy_published->domain->__toString());
                $dbdomain->setStsVersion("STSv1");
                $dbdomain->setStsMode("enforce");
                $dbdomain->setStsMaxAge(86400);
                $dbdomain->setMailhost($dmarcreport->policy_published->domain->__toString());
                $this->em->persist($dbdomain);
                $this->em->flush();
            }
            $dbreport = new DMARC_Reports;
            $dbreport->setBeginTime((new \DateTime)->setTimestamp($dmarcreport->report_metadata->date_range->begin->__toString()));
            $dbreport->setEndTime((new \DateTime)->setTimestamp($dmarcreport->report_metadata->date_range->end->__toString()));
            $dbreport->setOrganisation($dmarcreport->report_metadata->org_name->__toString());
            $dbreport->setEmail($dmarcreport->report_metadata->email->__toString());
            $dbreport->setContactInfo($dmarcreport->report_metadata->extra_contact_info->__toString());
            $dbreport->setExternalId($dmarcreport->report_metadata->report_id->__toString());
            $dbreport->setDomain($dbdomain);
            $dbreport->setPolicyAdkim($dmarcreport->policy_published->adkim->__toString());
            $dbreport->setPolicyAspf($dmarcreport->policy_published->aspf->__toString());
            $dbreport->setPolicyP($dmarcreport->policy_published->p->__toString());
            $dbreport->setPolicySp($dmarcreport->policy_published->sp->__toString());
            $dbreport->setPolicyPct($dmarcreport->policy_published->pct->__toString());
            $this->em->persist($dbreport);
            $this->em->flush();
            
            foreach($dmarcreport->record as $record){
                $stats['new_dmarc_records']++;

                $dbrecord = new DMARC_Records;
                $dbrecord->setReport($dbreport);
                $dbrecord->setSourceIp($record->row->source_ip->__toString());
                $dbrecord->setCount($record->row->count->__toString());
                $dbrecord->setPolicyDisposition(intval($record->row->policy_evaluated->disposition->__toString()));
                $dbrecord->setPolicyDkim($record->row->policy_evaluated->dkim->__toString());
                $dbrecord->setPolicySpf($record->row->policy_evaluated->spf->__toString());
                $dbrecord->setEnvelopeTo($record->identifiers->envelope_to->__toString());
                $dbrecord->setEnvelopeFrom($record->identifiers->envelope_from->__toString());
                $dbrecord->setHeaderFrom($record->identifiers->header_from->__toString());
                $this->em->persist($dbrecord);
                $this->em->flush();
                
                foreach($record->auth_results->dkim as $dkim_result){
                    $stats['new_dmarc_results']++;

                    $dbresult = new DMARC_Results;
                    $dbresult->setRecord($dbrecord);
                    $dbresult->setDomain($dkim_result->domain->__toString());
                    $dbresult->setType('dkim');
                    $dbresult->setResult($dkim_result->result->__toString());
                    $dbresult->setDkimSelector($dkim_result->selector->__toString());
                    $this->em->persist($dbresult);
                }

                foreach($record->auth_results->spf as $spf_result){
                    $stats['new_dmarc_results']++;

                    $dbresult = new DMARC_Results;
                    $dbresult->setRecord($dbrecord);
                    $dbresult->setDomain($spf_result->domain->__toString());
                    $dbresult->setType('spf');
                    $dbresult->setResult($spf_result->result->__toString());
                    $this->em->persist($dbresult);
                }
                $this->em->flush();
            }
        }

        foreach($mailresult['reports']['smtptls_reports'] as $smtptlsreport){
            $stats['new_smtptls_reports']++;

            $dbreport = new SMTPTLS_Reports;
            $dbreport->setOrganisation($smtptlsreport->{'organization-name'});
            $dbreport->setContactInfo($smtptlsreport->{'contact-info'});
            $dbreport->setExternalId($smtptlsreport->{'report-id'});
            $dbreport->setBeginTime(new \DateTime($smtptlsreport->{'date-range'}->{'start-datetime'}));
            $dbreport->setEndTime(new \DateTime($smtptlsreport->{'date-range'}->{'end-datetime'}));
            $this->em->persist($dbreport);
            $this->em->flush();

            foreach($smtptlsreport->policies as $policy){
                $stats['new_smtptls_policies']++;
                
                $domain_repository = $this->em->getRepository(Domains::class);
                $dbdomain = $domain_repository->findOneBy(array('fqdn' => $policy->policy->{'policy-domain'}));
                if(!$dbdomain){
                    $stats['new_domains']++;

                    $dbdomain = new Domains;
                    $dbdomain->setFqdn($policy->policy->{'policy-domain'});
                    $dbdomain->setStsVersion("STSv1");
                    $dbdomain->setStsMode("enforce");
                    $dbdomain->setStsMaxAge(86400);
                    $dbdomain->setMailhost($policy->policy->{'policy-domain'});
                    $this->em->persist($dbdomain);
                    $this->em->flush();
                }

                $dbpolicy = new SMTPTLS_Policies;
                $dbpolicy->setReport($dbreport);
                $dbpolicy->setPolicyType($policy->policy->{'policy-type'});
                $dbpolicy->setPolicyDomain($dbdomain);
                $dbpolicy->setSummarySuccessfulCount($policy->summary->{'total-successful-session-count'});
                $dbpolicy->setSummaryFailedCount($policy->summary->{'total-failure-session-count'});
                $this->em->persist($dbpolicy);
                $this->em->flush();

                if(property_exists($policy->policy, 'policy-string')){
                    $dbpolicy->setPolicyStringVersion(str_replace("version: ","",array_slice(preg_grep('/^version:.*/', $policy->policy->{'policy-string'}), 0, 1)[0]));
                    $dbpolicy->setPolicyStringMode(str_replace("mode: ","",array_slice(preg_grep('/^mode:.*/', $policy->policy->{'policy-string'}), 0, 1)[0]));
                    $dbpolicy->setPolicyStringMaxage(str_replace("max_age: ","",array_slice(preg_grep('/^max_age:.*/', $policy->policy->{'policy-string'}), 0, 1)[0]));
                    $mxrecords=str_replace("mx: ","",array_values(preg_grep('/^mx:.*/', $policy->policy->{'policy-string'})));
                    $this->em->persist($dbpolicy);
                    $this->em->flush();
                    
                    $i=0;
                    foreach($mxrecords as $mxrecord){
                        $stats['new_smtptls_mxmapping']++;
                        $i++;

                        $mx_repository = $this->em->getRepository(MXRecords::class);
                        $dbmxrecord = $mx_repository->findOneBy(array('domain' => $dbdomain, 'name' => $mxrecord));
                        if(!$dbmxrecord){
                            $stats['new_mxrecords']++;
        
                            $dbmxrecord = new MXRecords;
                            $dbmxrecord->setDomain($dbdomain);
                            $dbmxrecord->setName($mxrecord);
                            $dbmxrecord->setInSts(true);
                            $this->em->persist($dbmxrecord);
                            $this->em->flush();
                        }

                        $dbmx = new SMTPTLS_MXRecords;
                        $dbmx->setMXRecord($dbmxrecord);
                        $dbmx->setPolicy($dbpolicy);
                        $dbmx->setPriority($i);
                        $this->em->persist($dbmx);
                        $this->em->flush();
                    }
                }
                if(property_exists($policy, 'failure-details')){
                    foreach($policy->{'failure-details'} as $failure){
                        $stats['new_smtptls_failuredetails']++;

                        $mx_repository = $this->em->getRepository(MXRecords::class);
                        $dbmxrecord = $mx_repository->findOneBy(array('domain' => $dbdomain, 'name' => $mxrecord));
                        if(!$dbmxrecord){
                            $stats['new_mxrecords']++;
        
                            $dbmxrecord = new MXRecords;
                            $dbmxrecord->setDomain($dbdomain);
                            $dbmxrecord->setName($mxrecord);
                            $this->em->persist($dbmxrecord);
                            $this->em->flush();
                        }

                        $dbfailure = new SMTPTLS_FailureDetails;
                        $dbfailure->setPolicy($dbpolicy);
                        $dbfailure->setResultType($failure->{'result-type'});
                        $dbfailure->setSendingMtaIp($failure->{'sending-mta-ip'});
                        $dbfailure->setReceivingIp($failure->{'receiving-ip'});
                        $dbfailure->setReceivingMxHostname($dbmxrecord);
                        $dbfailure->setFailedSessionCount($failure->{'failed-session-count'});
                        $this->em->persist($dbfailure);
                        $this->em->flush();
                    }
                }
            }
        }

        $message = 'Mailbox checked: '.$stats['new_emails'].' new emails ('.$stats['new_domains'].' domains, '.$stats['new_mxrecords'].' mx), '.$stats['new_dmarc_reports'].' new dmarc reports ('.$stats['new_dmarc_records'].' records, '.$stats['new_dmarc_results'].' results), '.$stats['new_smtptls_reports'].' new smtptls reports ('.$stats['new_smtptls_policies'].' policies, '.$stats['new_smtptls_mxmapping'].' mxmapping, '.$stats['new_smtptls_failuredetails'].' failure details)';

        $log = new Logs;
        $log->setTime(new \DateTime);
        $log->setMessage($message);
        $this->em->persist($log);
        $this->em->flush();

        $io->success($message);

        return Command::SUCCESS;
    }

    private function open_mailbox(Imap $imap):array
    {
        $num_emails=0;
        $mailbox = $imap->get('default');
        $mailsIds = $mailbox->searchMailbox('UNSEEN');
        $dmarc_reports = array();
        $smtptls_reports = array();
        foreach($mailsIds as $mailId) {
            $num_emails++;
            $mail = $mailbox->getMail($mailId);
            $attachments = $mail->getAttachments();
            foreach ($attachments as $attachment) {
                $new_reports = $this->open_archive($attachment->filePath);
                $dmarc_reports = array_merge($dmarc_reports,$new_reports['dmarc_reports']);
                $smtptls_reports = array_merge($smtptls_reports,$new_reports['smtptls_reports']);
                unlink($attachment->filePath);
            }
            if ($_ENV['DELETE_MAILS'] == 'true') {
                $mailbox->deleteMail($mailId);
            }
        }
        return array('num_emails' => $num_emails, 'reports' => array('dmarc_reports' => $dmarc_reports, 'smtptls_reports' => $smtptls_reports));
    }

    private function open_archive($file): array
    {
        $dmarc_reports = array();
        $smtptls_reports = array();
        $ziparchive = new \ZipArchive;
        $filecontents = null;
        
        if ($ziparchive->open($file) === TRUE) {
            for($i=0; $i<$ziparchive->numFiles; $i++){
                $stat = $ziparchive->statIndex($i);
                $filecontents = file_get_contents("zip://$file#".$stat["name"]);
            }
        } elseif($gzarchive = gzopen($file, 'r')) {
            $gzcontents=null;
            while (!feof($gzarchive)) {
                $gzcontents .= gzread($gzarchive, filesize($file));
            }
            $filecontents = $gzcontents;
        }

        if(substr($filecontents, 0, 5) == "<?xml") {
            //Expecting an DMARC XML Report
            $dmarc_reports[] = new \SimpleXMLElement($filecontents);
        }
        elseif($this->isJson($filecontents)) {
            //Expecting an SMTP-TLS JSON Report
            $smtptls_reports[] = json_decode($filecontents);
        }

        return array('dmarc_reports' => $dmarc_reports, 'smtptls_reports' => $smtptls_reports);
    }

    private function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
     }
}
