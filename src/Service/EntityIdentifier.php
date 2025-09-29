<?php namespace Lalamefine\Autoadmin\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Proxy;

class EntityIdentifier {

    public function __construct(private EntityManagerInterface $em)
    { }

    public function makeTextIdentifierFromClassAndId(string $fqcn, mixed $id): string
    {
        $classMetadata = $this->em->getClassMetadata($fqcn);
        $identification = $classMetadata->getIdentifier()[0] ?? null;
        if (!$identification) {
            return $classMetadata->getName() . '#' . $id . ' (no identifier)';
        }
        try {
            $e = $this->em->getRepository($fqcn)->find($id);
        } catch (\Throwable $th) {
            throw new \Exception("Failed to find entity $fqcn with id $id", 1, $th);
        }
        if (!$e && $id !== null) {
            return $classMetadata->getName() . '#' . $id . ' (not found)';
        }
        if ($e) {
            try {
                $ref = new \ReflectionClass($e);
                $prop = $ref->getProperty($identification);
                $prop->setAccessible(true);
                $idValue = $prop->getValue($e);
            } catch (\Throwable $th) {
                $idValue = null;
            }

            if(is_object($idValue)){
                if (method_exists($idValue, '__toString')) {
                    return $ref->getShortName() . $this->inColoredDiv($identification.'->'.$idValue->__toString());
                } else {
                    return $ref->getShortName() . $this->inColoredDiv($identification.'->'.get_class($idValue));
                }
            } else {
                if ($e instanceof \Stringable || method_exists($e, '__toString')) {
                    return $ref->getShortName() . '#' . $id . $this->inColoredDiv( $e->__toString() );
                } else if (method_exists($e, 'getName')) {
                    return $ref->getShortName() . '#' . $id . $this->inColoredDiv( $e->getName() );
                } else if (method_exists($e, 'getTitle')) {
                    return $ref->getShortName() . '#' . $id . $this->inColoredDiv( $e->getTitle() );
                } 
                return $ref->getShortName() . '#' . $id;
            } 
            return $ref->getShortName() . '#?';
        }else{
            $ref = new \ReflectionClass($fqcn);
        }
        return $ref->getShortName() . '#' . $id;
    }

    public function arrayToIdLabelMap($arrayOrCollection, $identifierField = 'id'): array
    {
        $collection = is_array($arrayOrCollection) ? $arrayOrCollection : $arrayOrCollection->toArray();
        return array_map(function($e) use ($identifierField) {
            $id = null; $name = '';
            $ref = new \ReflectionObject($e);
            if ($ref->hasProperty($identifierField)) {
                $prop = $ref->getProperty($identifierField);
                $prop->setAccessible(true);
                $id = $prop->getValue($e);
            }
            if(method_exists($e, '__toString')){
                $name = $ref->getShortName(). '#' . $id . $this->inColoredDiv((string)$e);
            } else if(method_exists($e, 'getName')){
                $name = $ref->getShortName(). '#' . $id . $this->inColoredDiv($e->getName());
            } else if(method_exists($e, 'getTitle')){
                $name = $ref->getShortName(). '#' . $id . $this->inColoredDiv($e->getTitle());
            } else {
                $name = $ref->getShortName(). '#' . $id;
            }
            return [
                'id' => $id,
                'name' => $name
            ];
        }, $collection);
    } 

    function inColoredDiv($text) {
        return "<div class=\"rounded-xl bg-blue-100 ms-1 px-1\">$text</div>";
    }

    public function getEntityId(object $entity): mixed
    {
        try {
            if ($entity instanceof Proxy) {
                $entityClass = get_parent_class($entity);
                $entity->__load(); // force loading if it's a proxy
            } else {
                $entityClass = get_class($entity);
            }
            $ref = new \ReflectionClass($entityClass);
            $identifierField = $this->em->getClassMetadata(get_class($entity))->getIdentifier()[0] ?? 'id';
            if ($ref->hasProperty($identifierField)) {
                $prop = $ref->getProperty($identifierField);
                $prop->setAccessible(true);
                return $prop->getValue($entity);
            }
        } catch (\Throwable $th) {
            throw new \Exception("Failed to get entity ID", 1, $th);
        }
        return null;
    }
}