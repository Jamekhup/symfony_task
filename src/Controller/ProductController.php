<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\CsvUploadType;
use App\Form\ProductSearchType;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/')]
final class ProductController extends AbstractController
{
    #[Route(name: 'app_product_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator, EntityManagerInterface $entityManager, ProductRepository $productRepository): Response
    {

        $form = $this->createForm(ProductSearchType::class);
        $form->handleRequest($request);

        $searchTerm = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $searchTerm = $data['name'];
        }

        $queryBuilder = $entityManager->getRepository(Product::class)
            ->createQueryBuilder('p');

        if (!empty($searchTerm)) {
            $queryBuilder->where('p.name LIKE :name')
                ->setParameter('name', '%' . $searchTerm . '%');
        }

        $query = $queryBuilder->getQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('product/index.html.twig', [
            'pagination' => $pagination,
            'form' => $form->createView(),
            'searchTerm' => $searchTerm
        ]);
        // $form = $this->createForm(ProductSearchType::class);
        // $form->handleRequest($request);

        // return $this->render('product/index.html.twig', [
        //     'products' => $productRepository->findAll(),
        //     'form' => $form->createView(),
        // ]);
    }

    #[Route('/create', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        $product->setCreatedAt(new \DateTime());

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($product);
            $entityManager->flush();

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $product->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($product);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }




    #[Route('/product/import', name: 'import_product')]
    public function import(Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CsvUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $csvFile = $form->get('csv_file')->getData();

            if ($csvFile) {
                $originalFilename = pathinfo($csvFile->getClientOriginalName(), PATHINFO_FILENAME);
                $newFilename = $originalFilename . '-' . uniqid() . '.' . $csvFile->guessExtension();

                try {
                    $csvFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/',
                        $newFilename
                    );

                    $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $newFilename;
                    $this->processCsv($filePath, $entityManager);

                    $this->addFlash(
                        'info',
                        'Product imported successfully!'
                    );

                    return $this->redirectToRoute('app_product_index');
                } catch (FileException $e) {
                    $this->addFlash('error', 'There was an error uploading the file.');
                }
            }
        }

        return $this->render('product/import.html.twig', [
            'form' => $form->createView(),
        ]);
    }



    private function processCsv(string $filePath, EntityManagerInterface $entityManager): void
    {
        if (($handle = fopen($filePath, 'r')) !== false) {

            fgetcsv($handle);

            while (($data = fgetcsv($handle, 1000, ',')) !== false) {

                $name = $data[0];
                $price = $data[1];
                $stock = $data[2];
                $description = $data[3];

                $product = new Product();
                $product->setName($name);
                $product->setPrice($price);
                $product->setStock($stock);
                $product->setDescription($description);
                $product->setCreatedAt(new \DateTimeImmutable());

                $entityManager->persist($product);
            }

            $entityManager->flush();

            fclose($handle);
        }
    }


    #[Route('/product/export', name: 'export_product')]
    public function export(EntityManagerInterface $em): Response
    {
        $filesystem = new Filesystem();
        $exportDir = sys_get_temp_dir() . '/exported_files';
        $filesystem->mkdir($exportDir);

        $offset = 0;
        $limit = 10;
        $fileIndex = 1;

        do {
            $query = $em->createQueryBuilder()
                ->select('e')
                ->from('App\Entity\Product', 'e')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery();

            $results = $query->getResult();

            if (empty($results)) {
                break;
            }

            $filename = $exportDir . "/export_part_$fileIndex.csv";
            $handle = fopen($filename, 'w+');

            fputcsv($handle, ['ID', 'Name', 'Price', 'Stock', 'Description', 'Created At']);

            foreach ($results as $result) {
                fputcsv($handle, [
                    $result->getId(),
                    $result->getName(),
                    $result->getPrice(),
                    $result->getStock(),
                    $result->getDescription(),
                    $result->getCreatedAt()->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);

            $offset += $limit;
            $fileIndex++;
        } while (true);

        $zipFile = $exportDir . '/exported_files.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE) === true) {
            foreach (glob($exportDir . '/*.csv') as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
        }

        $response = new Response(file_get_contents($zipFile));
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="exported_files.zip"');

        $filesystem->remove($exportDir);

        return $response;
    }
}
