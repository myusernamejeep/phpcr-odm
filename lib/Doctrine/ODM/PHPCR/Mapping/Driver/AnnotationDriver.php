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

namespace Doctrine\ODM\PHPCR\Mapping\Driver;

use Doctrine\Common\Annotations\AnnotationReader,
    Doctrine\Common\Annotations\Reader,
    Doctrine\Common\Persistence\Mapping\Driver\AnnotationDriver as AbstractAnnotationDriver,
    Doctrine\Common\Persistence\Mapping\ClassMetadata,
    Doctrine\Common\Persistence\Mapping\Driver\MappingDriver,
    Doctrine\ODM\PHPCR\Event,
    Doctrine\ODM\PHPCR\Mapping\Annotations as ODM,
    Doctrine\ODM\PHPCR\Mapping\MappingException;

/**
 * The AnnotationDriver reads the mapping metadata from docblock annotations.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.org
 * @since       1.0
 * @author      Jordi Boggiano <j.boggiano@seld.be>
 * @author      Pascal Helfenstein <nicam@nicam.ch>
 * @author      Daniel Barsotti <daniel.barsotti@liip.ch>
 * @author      David Buchmann <david@liip.ch>
 */
class AnnotationDriver extends AbstractAnnotationDriver implements MappingDriver
{

    /**
     * {@inheritdoc}
     *
     * Document annotation classes, ordered by precedence.
     */
    protected $entityAnnotationClasses = array(
        'Doctrine\\ODM\\PHPCR\\Mapping\\Annotations\\Document' => 0,
        'Doctrine\\ODM\\PHPCR\\Mapping\\Annotations\\MappedSuperclass' => 1,
    );

    /**
     * {@inheritdoc}
     */
    public function loadMetadataForClass($className, ClassMetadata $metadata)
    {
        $reflClass = $metadata->getReflectionClass();

        $documentAnnots = array();
        foreach ($this->reader->getClassAnnotations($reflClass) as $annot) {
            foreach ($this->entityAnnotationClasses as $annotClass => $i) {
                if ($annot instanceof $annotClass) {
                    $documentAnnots[$i] = $annot;
                }
            }
        }
        if (!$documentAnnots) {
             throw MappingException::classIsNotAValidDocument($className);
        }

        // find the winning document annotation
        ksort($documentAnnots);

        $documentAnnot = reset($documentAnnots);

        if (isset($documentAnnot->versionable) && $documentAnnot->versionable) {
            $metadata->setVersioned($documentAnnot->versionable);
        }

        $metadata->setNodeType($documentAnnot->nodeType);

        if (isset($documentAnnot->referenceable) && $documentAnnot->referenceable) {
            $metadata->setReferenceable(true);
        }

        if ($documentAnnot->repositoryClass) {
            $metadata->setCustomRepositoryClassName($documentAnnot->repositoryClass);
        }

        if ($documentAnnot->translator) {
            $metadata->setTranslator($documentAnnot->translator);
        }

        foreach ($reflClass->getProperties() as $property) {
            if (($metadata->isMappedSuperclass && !$property->isPrivate())
                || $metadata->isInheritedField($property->name)
            ) {
                continue;
            }

            $mapping = array();
            $mapping['fieldName'] = $property->getName();

            foreach ($this->reader->getPropertyAnnotations($property) as $fieldAnnot) {
                if ($fieldAnnot instanceof ODM\Property) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    $metadata->mapField($mapping);
                } elseif ($fieldAnnot instanceof ODM\Id) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    $metadata->mapId($mapping);
                } elseif ($fieldAnnot instanceof ODM\Node) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    $metadata->mapNode($mapping);
                } elseif ($fieldAnnot instanceof ODM\Nodename) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    $metadata->mapNodename($mapping);
                } elseif ($fieldAnnot instanceof ODM\ParentDocument) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    $metadata->mapParentDocument($mapping);
                } elseif ($fieldAnnot instanceof ODM\Child) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    $metadata->mapChild($mapping);
                } elseif ($fieldAnnot instanceof ODM\Children) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    $metadata->mapChildren($mapping);
                } elseif ($fieldAnnot instanceof ODM\ReferenceOne) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    $metadata->mapManyToOne($mapping);
                } elseif ($fieldAnnot instanceof ODM\ReferenceMany) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    $metadata->mapManyToMany($mapping);
                } elseif ($fieldAnnot instanceof ODM\Referrers) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    $metadata->mapReferrers($mapping);
                } elseif ($fieldAnnot instanceof ODM\Locale) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    $metadata->mapLocale($mapping);
                } elseif ($fieldAnnot instanceof ODM\VersionName) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    $metadata->mapVersionName($mapping);
                } elseif ($fieldAnnot instanceof ODM\VersionCreated) {
                    $mapping = array_merge($mapping, (array) $fieldAnnot);
                    $metadata->mapVersionCreated($mapping);
                }

                if (!isset($mapping['name'])) {
                    $mapping['name'] = $property->getName();
                }
            }
        }

        foreach ($reflClass->getMethods() as $method) {
            if ($method->isPublic() && $method->getDeclaringClass()->getName() == $metadata->name) {
                foreach ($this->reader->getMethodAnnotations($method) as $annot) {
                    if ($annot instanceof ODM\PrePersist) {
                        $metadata->addLifecycleCallback($method->getName(), Event::prePersist);
                    } elseif ($annot instanceof  ODM\PostPersist) {
                        $metadata->addLifecycleCallback($method->getName(), Event::postPersist);
                    } elseif ($annot instanceof ODM\PreUpdate) {
                        $metadata->addLifecycleCallback($method->getName(), Event::preUpdate);
                    } elseif ($annot instanceof ODM\PostUpdate) {
                        $metadata->addLifecycleCallback($method->getName(), Event::postUpdate);
                    } elseif ($annot instanceof ODM\PreRemove) {
                        $metadata->addLifecycleCallback($method->getName(), Event::preRemove);
                    } elseif ($annot instanceof ODM\PostRemove) {
                        $metadata->addLifecycleCallback($method->getName(), Event::postRemove);
                    } elseif ($annot instanceof ODM\PreLoad) {
                        $metadata->addLifecycleCallback($method->getName(), Event::preLoad);
                    } elseif ($annot instanceof  ODM\PostLoad) {
                        $metadata->addLifecycleCallback($method->getName(), Event::postLoad);
                    }
                }
            }
        }

        // Check there is a @Locale annotation for translatable documents
        if (count($metadata->translatableFields)) {
            if (!isset($metadata->localeMapping)) {
                throw new MappingException("You must define a @Locale field for translatable document '$className'");
            }
        }
    }

}
