<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Category;
use App\Form\CategoryType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Service\CategoryService;
use App\Entity\Document;
use App\Form\DocumentType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Entity\DocumentFile;
use App\Form\DocumentFileType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class ManageController
 *
 * @Route("/manage")
 */
class ManageController extends AbstractController {

    /**
     * @var CategoryService
     */
    private $categoryService;
    
    /**
     * @param CategoryService $categoryService
     */
    public function __construct(CategoryService $categoryService) {
        $this->categoryService = $categoryService;
    }
    
    /**
     * @Route("/delete_document/{documentId}/{confirm}", name="doc_manage_delete_document")
     */
    public function deleteDocument(Request $request, EntityManagerInterface $entityManager, int $documentId, bool $confirm = null): Response {
        $document = $this->categoryService->getDocument($documentId);
        $categoryId = $document->getCategory()->getId();
        if (!$document) {
            return $this->returnNotFound();
        }
        
        if ($confirm) {
            $this->categoryService->deleteDocument($document);
        }
        return $this->redirectToRoute('doc_category', ['categoryId' => $categoryId]);
    }
    
    /**
     * @Route("/delete_category/{categoryId}/{confirm}", name="doc_manage_delete_category")
     */
    public function deleteCategory(Request $request, EntityManagerInterface $entityManager, int $categoryId, bool $confirm = null): Response {
        $category = $this->categoryService->getCategory($categoryId);
        if (!$category) {
            return $this->returnNotFound();
        }
        $parentCategoryId = null;
        if ($category) {
            $parentCategory = $category->getParentCategory();
            $parentCategoryId = $parentCategory ? $parentCategory->getId() : null;
        }
        
        if ($confirm) {
            $this->categoryService->deleteCategory($category);
        }
        
        return $this->redirectToRoute('doc_category', $parentCategoryId ? ['categoryId' => $parentCategoryId] : []);
    }
    
    /**
     * @Route("/delete_file/{fileId}/{confirm}", name="doc_manage_delete_file")
     */
    public function deleteFile(Request $request, EntityManagerInterface $entityManager, $fileId, bool $confirm = null): Response {
        $documentFile = $this->categoryService->getDocumentFile($fileId);
        if (!$documentFile) {
            return $this->returnNotFound();
        }
        $document = $documentFile->getDocument();
        
        if ($confirm) {
            $this->categoryService->deleteDocumentFile($documentFile);
        }
        
        return $this->redirectToRoute('doc_document', ['documentId' => $document->getId()]);
        
    }
    
    /**
     * @Route("/edit_file/{documentId}/{fileId}", name="doc_manage_edit_file")
     */
    public function editFile(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, $documentId, $fileId = null): Response
    {
        $document = $this->categoryService->getDocument($documentId);
        if (!$document) {
            return $this->returnNotFound();
        }
        if ($fileId) {
            $documentFile = $this->categoryService->getDocumentFile($fileId);
        } else{
            $documentFile = new DocumentFile();
        }
        if (!$documentFile || ($documentFile->getId() && $documentFile->getDocument()->getId() != $documentId)) {
            return $this->returnNotFound();
        }
        
        // Tworzymy formularz
        $form = $this->createForm(DocumentFileType::class, $documentFile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$fileId) {
                /* @var $file UploadedFile */
                $file = $form->get('file')->getData();
                if ($file) {
                    // Generowanie unikalnej nazwy pliku
                    $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $exp = explode('.', $file->getClientOriginalName());
                    $extenion = count($exp) >= 2 ? end($exp) : '';
                    
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $extenion;
                    
                    try {
                        $pathPart = $this->categoryService->generateFileDir($document);
                        $dir = $this->getParameter('uploads_directory') . "/" . $pathPart;

                        if (!is_dir($dir)) {
                            mkdir($dir, 0777, true);
                        }

                        $file->move(
                            $dir,
                            $newFilename
                        );
                        $documentFile->setSize(filesize($dir . "/" . $newFilename));
                        $documentFile->setPath($pathPart . "/" . $newFilename);

                        $this->addFlash('success', 'Plik został pomyślnie przesłany!');
                    } catch (FileException $e) {
                        $this->addFlash('danger', 'Błąd podczas przesyłania pliku.');
                    }
                }
            }
            if ($fileId || isset($file)) {
                $documentFile->setModifiedAt(new \DateTime());
                $documentFile->setDocument($document);
                // Zapisanie encji
                $entityManager->persist($documentFile);
                $entityManager->flush();
            }
            return $this->redirectToRoute('doc_document', ['documentId' => $document->getId()]);
        }

        return $this->render('manage/document_file/file_edit.html.twig', [
            'document' => $document,
            'parentCategory' => $document->getCategory(),
            'breadcrumbs' => $this->categoryService->getBreadcrumbs($document->getCategory(), $document),
            'documentFileId' => $fileId,
            'documentFile' => $documentFile,
            'form' => $form->createView(),
        ]);
    }


    /**
     * @Route("/edit_document/{categoryId}/{documentId}", name="doc_manage_edit_document")
     */
    public function editDocument(Request $request, EntityManagerInterface $entityManager, $categoryId, $documentId = null): Response {
        $category = $this->categoryService->getCategory($categoryId);
        if ($documentId) {
            $document = $this->categoryService->getDocument($documentId);
            if (!$category || !$document || $document->getCategory()->getId() != $categoryId) {
                return $this->returnNotFound();
            }
        } else {
            $document = new Document();
            $document->setCategory($category);
        }
        
        $form = $this->createForm(DocumentType::class, $document);

        // Obsługa przesłania formularza
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            
            $document->setModifiedAt(new \DateTimeImmutable());
            
            $entityManager->persist($document);
            // Zapisanie zmian w bazie danych
            $entityManager->flush();

            // Przekierowanie lub inna logika
            return $this->redirectToRoute('doc_category', ['categoryId' => $categoryId]);
        }

        return $this->render('manage/document/edit.html.twig', [
            'form' => $form->createView(),
            'breadcrumbs' => $this->categoryService->getBreadcrumbs($category),
            'parentCategory' => $category,
            'categoryId' => $categoryId // Przekazywanie ID kategorii
        ]);        
    }
    
    /**
     * @Route("/edit_category/{parentCategoryId}/{categoryId}", name="doc_manage_edit_category")
     */
    public function editCategory(Request $request, EntityManagerInterface $entityManager, $parentCategoryId = null, $categoryId = null): Response {
        $parentCategory = $this->categoryService->getCategory($parentCategoryId);
        if ($parentCategoryId && !$parentCategory) {
            return $this->returnNotFound();
        }
        
        if (isset($categoryId)) {
            $category = $this->categoryService->getCategory($categoryId);
        } else {
            // Tworzymy nową instancję kategorii
            $category = new Category();
        }

        // Tworzymy formularz
        $form = $this->createForm(CategoryType::class, $category);

        // Obsługujemy żądanie (jeśli formularz został wysłany)
        $form->handleRequest($request);

        // Jeśli formularz jest poprawnie przesłany i walidowany
        if ($form->isSubmitted() && $form->isValid()) {
            $category->setParentCategory($parentCategory);
            $category->setModifiedAt(new \DateTime());
            
            $entityManager->persist($category);
            $entityManager->flush();

            // Przekierowanie po zapisie
            return $this->redirectToRoute('doc_category', $parentCategoryId ? ['categoryId' => $parentCategoryId] : []);
        }

        // Renderowanie formularza
        return $this->render('manage/category/edit.html.twig', [
            'form' => $form->createView(),
            'parentCategory' => $parentCategory,
            'breadcrumbs' => $categoryId ? $this->categoryService->getBreadcrumbs($category) : $this->categoryService->getBreadcrumbs($parentCategory),
        ]);
    }
    
    protected function returnNotFound() {
        throw new NotFoundHttpException("404 Not Found");
    }
    
}
