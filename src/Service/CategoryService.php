<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Repository\CategoryRepository;
use App\Entity\Category;
use App\Repository\DocumentRepository;
use App\Entity\Document;
use App\Repository\DocumentFileRepository;
use App\Entity\DocumentFile;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CategoryService {
    
    /**
     * @var EntityManagerInterface
     */
    private $em;
    
    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;
    
    /**
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em, ParameterBagInterface $parameterBag) {
        $this->em = $em;
        $this->parameterBag = $parameterBag;
    }
    
    /**
     * @param int $documentId
     * @return Document|null
     */
    public function getDocument(int $documentId = null): ?Document {
        $document = null;
        if (isset($documentId)) {
            /* @var $documentRepository DocumentRepository */
            $documentRepository = $this->em->getRepository(Document::class);
            $document = $documentRepository->find($documentId);
        }
        return $document;
    }
    
    /**
     * @param int $fileId
     * @return Document|null
     */
    public function getDocumentFile(int $fileId): ?DocumentFile {
        $documentFile = null;
        if (isset($fileId)) {
            /* @var $documentFileRepository DocumentFileRepository */
            $documentFileRepository = $this->em->getRepository(DocumentFile::class);
            $documentFile = $documentFileRepository->find($fileId);
        }
        return $documentFile;
    }
    
    /**
     * @param int $categoryId
     * @return Category|null
     */
    public function getCategory(int $categoryId = null): ?Category {
        $category = null;
        if (isset($categoryId)) {
            /* @var $categoryRepository CategoryRepository */
            $categoryRepository = $this->em->getRepository(Category::class);
            $category = $categoryRepository->find($categoryId);
        }
        return $category;
    }
    
    /**
     * Deletes category and all children
     * 
     * @param Category $category
     */
    public function deleteCategory(Category $category) {
        $this->em->beginTransaction();
        
        $childCategories = $this->getCategories($category->getId());
        /* @var $childCategory Category */
        foreach($childCategories as $childCategory) {
            $this->deleteCategory($childCategory);
        }
        
        foreach($this->getDocuments($category->getId()) as $categoryDocument) {
            $this->deleteDocument($categoryDocument);
        }
        
        $this->em->remove($category);
        $this->em->flush();
        
        $this->em->commit();
    }
    
    /**
     * 
     * @param Document $document
     * @return Document|null
     */
    public function deleteDocument(Document $document) {
        $this->em->beginTransaction();

        $documentFiles = $this->getDocumentFiles($document->getId());
        foreach($documentFiles as $documentFile) {
            $this->deleteDocumentFile($documentFile);
        }
        
        $this->em->remove($document);
        $this->em->flush();
        
        $this->em->commit();
    }
    
    /**
     * @param DocumentFile $documentFile
     */
    public function deleteDocumentFile(DocumentFile $documentFile) {
        $this->em->beginTransaction();

        $relativePath = $documentFile->getPath();
        $filePath = $this->parameterBag->get('uploads_directory') . "/" . $relativePath;
        
        if (is_file($filePath)) {
            unlink($filePath);
        }
        
        $this->em->remove($documentFile);
        $this->em->flush();
        
        $this->em->commit();
    }
    
    /**
     * @param Document $documentFile
     * @return string
     */
    public function generateFileDir(Document $documentFile): string {
        $treeParts = [$documentFile->getId()];
        
        $category = $documentFile->getCategory();
        $treeParts[] = $category->getId();
        
        /* @var $parentCategory Category */
        while ($parentCategory = ($category->getParentCategory())) {
            $treeParts[] = $parentCategory->getId();
            $category = $parentCategory;
        }
        
        $partsRev = array_reverse($treeParts);
        array_unshift($partsRev, "files");
        $dir = implode('/', $partsRev);
        
        return $dir;
    }
    
    /**
     * @param int $documentId
     * @return array|null
     */
    public function getDocumentFiles(int $documentId): array {
        /* @var $documentFileRepository DocumentFileRepository */
        $documentFileRepository = $this->em->getRepository(DocumentFile::class);
        
        $files = $documentFileRepository->findBy([
            'document' => $documentId,
        ], [
            'createdAt' => 'ASC',
        ]);
        
        return $files;
    }
    
    /**
     * @param int $categoryId
     * @return array
     */
    public function getDocuments(int $categoryId):array {
        /* @var $documentRepository DocumentRepository */
        $documentRepository = $this->em->getRepository(Document::class);
        
        $documents = $documentRepository->findBy([
            'category' => $categoryId,
        ], [
            'name' => 'ASC',
        ]);
        
        return $documents;
    }
    
    /**
     * @param Category $category [Optional]
     * @param Document $document [Optional]
     * @return array
     */
    public function getBreadcrumbs(Category $category = null, Document $document = null) {
        $breadcrumbs = [];
        $home = [
            'label' => 'Home',
            'href' => '/',
        ];
        $breadcrumbs[] = $home;
        
        $categories = [];
        if ($category) {
            $categories[] = $category;
            $categoryNode = $category;
            while ($parentCategory = ($categoryNode->getParentCategory())) {
                $categories[] = $parentCategory;
                $categoryNode = $parentCategory;
            }
        }
        
        if (!empty($categories)) {
            /* @var $cat Category */
            foreach(array_reverse($categories) as $cat) {
                $breadcrumbs[] = [
                    'label' => $cat->getName(),
                    'href' => '/category/' . $cat->getId(),
                ];
            }
        }
        if (isset($document)) {
            $breadcrumbs[] = [
                'label' => $document->getName(),
                'href' => null,
            ];
        }
        
        return $breadcrumbs;
        
    }
    
    /**
     * @param int $parentCategoryId
     * @return array|null
     */
    public function getCategories(int $parentCategoryId = null): ?array {
        /* @var $categoryRepository CategoryRepository */
        $categoryRepository = $this->em->getRepository(Category::class);
        
        $categories = $categoryRepository->findBy([
            'parentCategory' => $parentCategoryId,
        ], [
            'name' => 'ASC',
        ]);
        
        return $categories;
    }
    
}
