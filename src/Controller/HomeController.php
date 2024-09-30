<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\CategoryService;
use App\Entity\Category;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class HomeController extends AbstractController
{
    
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
     * @Route("/", name="doc_categories_index")
     */
    public function index(): Response
    {
        return $this->category();
    }
    
    /**
     * @Route("/document/{documentId}", name="doc_document")
     */
    public function document($documentId): Response
    {
        $document = $this->categoryService->getDocument($documentId);
        if (!$document) {
            return $this->returnNotFound();
        }
        
        $files = $this->categoryService->getDocumentFiles($documentId);
        
        return $this->render('home/document.html.twig', [
            'parentCategory' => $document->getCategory(),
            'document' => $document,
            'files' => $files,
            'breadcrumbs' => $this->categoryService->getBreadcrumbs($document->getCategory(), $document),
        ]);
    }
    
    /**
     * @Route("/file_download/{fileId}", name="doc_file_download")
     */
    public function fileDownload($fileId) {
        $documentFile = $this->categoryService->getDocumentFile($fileId);
        $documentPath = $dir = $this->getParameter('uploads_directory') . "/" . $documentFile->getPath();
        
        if (!is_file($documentPath)) {
            return $this->returnNotFound();
        }
        
        $response = new BinaryFileResponse($documentPath);
        $filename = $documentFile->getFilename();
        
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );

        return $response;   
    }
    
    /**
     * @Route("/category/{categoryId}", name="doc_category")
     */
    public function category($categoryId = null): Response
    {
        $category = $this->categoryService->getCategory($categoryId);
        if ($categoryId && !$category) {
            return $this->returnNotFound();
        }
        
        $categories = $this->categoryService->getCategories($categoryId);
        if ($categoryId) {
            $documents = $this->categoryService->getDocuments($categoryId);
        }
        
        return $this->render('home/category.html.twig', [
            'parentCategory' => $category,
            'categories' => $categories,
            'documents' => isset($documents) ? $documents : [],
            'breadcrumbs' => $this->categoryService->getBreadcrumbs($category),
        ]);
    }
    
    protected function returnNotFound() {
        throw new NotFoundHttpException("404 Not Found");
    }
    
}
