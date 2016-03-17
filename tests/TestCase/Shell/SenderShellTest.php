<?php

namespace EmailQueue\Test\Shell;
use EmailQueue\Shell\SenderShell;
use Cake\Mailer\Email;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Cake\Console\ConsoleOutput;
use Cake\Console\ConsoleIo;
use Cake\Network\Exception\SocketException;
use EmailQueue\Model\Table\EmailQueueTable;

/**
 * SenderShell Test Case
 *
 */
class SenderShellTest extends TestCase {

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.email_queue.email_queue'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp() {
        parent::setUp();
        $this->out = new ConsoleOutput();
        $this->io = new ConsoleIo($this->out);
        $this->Sender = $this->getMock(
            SenderShell::class,
            ['in', 'createFile', '_stop', '_newEmail'],
            [$this->io]
        );

        $this->Sender->params = [
            'limit' => 10,
            'template' => 'default',
            'layout' => 'default',
            'config' => 'default',
            'stagger' => false
        ];
        TableRegistry::get('EmailQueue', ['className' => EmailQueueTable::class]);
    }

    public function testMainAllFail() {
        $email = $this->getMock(Email::class, ['to', 'template', 'viewVars', 'send', 'subject', 'emailFormat', 'addHeaders']);

        $this->Sender->expects($this->exactly(3))->method('_newEmail')->with('default')->will($this->returnValue($email));
        $email->expects($this->exactly(3))->method('send')->will($this->returnValue(false));
        $email->expects($this->exactly(3))->method('to')->will($this->returnSelf());
        $email->expects($this->exactly(3))->method('subject')->with('Free dealz')->will($this->returnSelf());
        $email->expects($this->exactly(3))->method('emailFormat')->with('both')->will($this->returnSelf());
        $email->expects($this->exactly(3))->method('addHeaders')->with(['foo' => 'bar'])->will($this->returnSelf());

        $email->expects($this->exactly(3))->method('template')
            ->with('default', 'default')
            ->will($this->returnSelf());

        $email->expects($this->exactly(3))->method('viewVars')
            ->with(array('a' => 1, 'b' => 2))
            ->will($this->returnSelf());
        $this->Sender->main();

        $emails = TableRegistry::get('EmailQueue')->find()->where([
            'id IN' => ['email-1', 'email-2', 'email-3']
        ])->toList();
        $this->assertEquals(2, $emails[0]['send_tries']);
        $this->assertEquals(3, $emails[1]['send_tries']);
        $this->assertEquals(4, $emails[2]['send_tries']);

        $this->assertFalse($emails[0]['locked']);
        $this->assertFalse($emails[1]['locked']);
        $this->assertFalse($emails[2]['locked']);

        $this->assertFalse($emails[0]['sent']);
        $this->assertFalse($emails[1]['sent']);
        $this->assertFalse($emails[2]['sent']);
    }

    public function testMainAllWin() {
        $email = $this->getMock(Email::class, ['to', 'template', 'viewVars', 'send', 'subject', 'emailFormat']);

        $this->Sender->params['template'] = 'other';
        $this->Sender->params['layout'] = 'custom';
        $this->Sender->params['config'] = 'something';

        $this->Sender->expects($this->exactly(3))->method('_newEmail')
            ->with('something')
            ->will($this->returnValue($email));

        $email->expects($this->exactly(3))->method('send')->will($this->returnValue(true));
        $email->expects($this->exactly(3))->method('to')->will($this->returnSelf());
        $email->expects($this->exactly(3))->method('subject')->with('Free dealz')->will($this->returnSelf());
        $email->expects($this->exactly(3))->method('emailFormat')->with('both')->will($this->returnSelf());
        $email->expects($this->exactly(3))->method('template')
            ->with('other', 'custom')
            ->will($this->returnSelf());

        $email->expects($this->exactly(3))->method('viewVars')
            ->with(array('a' => 1, 'b' => 2))
            ->will($this->returnSelf());
        $this->Sender->main();

        $emails = TableRegistry::get('EmailQueue')->find()->where([
            'id IN' => ['email-1', 'email-2', 'email-3']
        ])->toList();

        $this->assertEquals(1, $emails[0]['send_tries']);
        $this->assertEquals(2, $emails[1]['send_tries']);
        $this->assertEquals(3, $emails[2]['send_tries']);

        $this->assertFalse($emails[0]['locked']);
        $this->assertFalse($emails[1]['locked']);
        $this->assertFalse($emails[2]['locked']);

        $this->assertTrue($emails[0]['sent']);
        $this->assertTrue($emails[1]['sent']);
        $this->assertTrue($emails[2]['sent']);
    }

    public function testMainAllFailWithException() {
        $email = $this->getMock(Email::class, ['to', 'template', 'viewVars', 'send', 'subject', 'emailFormat']);

        $this->Sender->expects($this->exactly(3))->method('_newEmail')->with('default')->will($this->returnValue($email));

        $email->expects($this->exactly(3))->method('send')->will($this->throwException(new SocketException('fail')));

        $email->expects($this->exactly(3))->method('to')->will($this->returnSelf());
        $email->expects($this->exactly(3))->method('subject')->with('Free dealz')->will($this->returnSelf());
        $email->expects($this->exactly(3))->method('emailFormat')->with('both')->will($this->returnSelf());
        $email->expects($this->exactly(3))->method('template')
            ->with('default', 'default')
            ->will($this->returnSelf());

        $email->expects($this->exactly(3))->method('viewVars')
            ->with(array('a' => 1, 'b' => 2))
            ->will($this->returnSelf());
        $this->Sender->main();

        $emails = TableRegistry::get('EmailQueue')->find()->where([
            'id IN' => ['email-1', 'email-2', 'email-3']
        ])->toList();
        $this->assertEquals(2, $emails[0]['send_tries']);
        $this->assertEquals(3, $emails[1]['send_tries']);
        $this->assertEquals(4, $emails[2]['send_tries']);

        $this->assertFalse($emails[0]['locked']);
        $this->assertFalse($emails[1]['locked']);
        $this->assertFalse($emails[2]['locked']);

        $this->assertFalse($emails[0]['sent']);
        $this->assertFalse($emails[1]['sent']);
        $this->assertFalse($emails[2]['sent']);
    }

    public function testClearLocks() {
        $emails = TableRegistry::get('EmailQueue');
        $emails->getBatch();
        $this->Sender->clearLocks();
        $this->assertEmpty($emails->findByLocked(true)->toArray());
    }

}
