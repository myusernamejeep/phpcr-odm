<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ODM\PHPCR;

/**
 * An DocumentRepository serves as a repository for documents with generic as well as
 * business specific methods for retrieving documents.
 *
 * This class is designed for inheritance and users can subclass this class to
 * write their own repositories with business-specific methods to locate documents.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Jordi Boggiano <j.boggiano@seld.be>
 * @author      Pascal Helfenstein <nicam@nicam.ch>
 */
class DocumentRepository
{
    /**
     * @var string
     */
    protected $documentName;

    /**
     * @var DocumentManager
     */
    protected $dm;

    /**
     * @var Doctrine\ODM\PHPCR\Mapping\ClassMetadata
     */
    protected $class;

    /**
     * Initializes a new <tt>DocumentRepository</tt>.
     *
     * @param DocumentManager $dm The DocumentManager to use.
     * @param ClassMetadata $classMetadata The class descriptor.
     */
    public function __construct($dm, Mapping\ClassMetadata $class)
    {
        $this->documentName = $class->name;
        $this->dm = $dm;
        $this->class = $class;
    }

    /**
     * Find a single document by its identifier
     *
     * @param string $path document path
     * @return object $document
     */
    public function find($path)
    {
        $uow = $this->dm->getUnitOfWork();
        $node = $this->dm->getPhpcrSession()->getNode($path);

        // TODO: catch exception and return null when not found?

        $hints = array();
        return $uow->createDocument($this->documentName, $node, $hints);
    }

    /**
     * @param  object $document
     * @return void
     */
    public function refresh($document)
    {
        $uow = $this->dm->getUnitOfWork();
        $response = $this->dm->getPhpcrSession()->findDocument($uow->getDocumentIdentifier($document));

        if ($response->status == 404) {
            throw new \Doctrine\ODM\PHPCR\DocumentNotFoundException();
        }

        $hints = array('refresh' => true);
        $uow->createDocument($this->documentName, $response->body, $hints);
    }

    /**
     * @return string
     */
    public function getDocumentName()
    {
        return $this->documentName;
    }

    /**
     * @return DocumentManager
     */
    public function getDocumentManager()
    {
        return $this->dm;
    }

    /**
     * @return Mapping\ClassMetadata
     */
    public function getClassMetadata()
    {
        return $this->class;
    }
}