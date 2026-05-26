<?php

namespace App\Controller;

use App\Entity\Stock;
use App\Entity\Products;
use App\Form\StockType;
use App\Repository\StockRepository;
use App\Repository\ProductsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/stock')]
#[IsGranted(new Expression("is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"))]
class StockController extends AbstractController
{
    #[Route('', name: 'stock_index', methods: ['GET'])]
    public function index(StockRepository $stockRepository): Response
    {
        $stocks = $stockRepository->findAll();

        return $this->render('stock/index.html.twig', [
            'stocks' => $stocks,
        ]);
    }

    #[Route('/new', name: 'stock_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ProductsRepository $productsRepository): Response
    {
        $stock = new Stock();
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $stock->setAddedBy($this->getUser());
            $stock->setAddedAt(new \DateTimeImmutable());

            // Update the product stock
            $product = $stock->getProduct();
            if ($product) {
                $currentStock = $product->getStock() ?? 0;
                $product->setStock($currentStock + $stock->getQuantity());
            }

            $entityManager->persist($stock);
            $entityManager->flush();

            $this->addFlash('success', 'Stock added successfully!');

            return $this->redirectToRoute('stock_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/new.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'stock_show', methods: ['GET'])]
    public function show(Stock $stock): Response
    {
        return $this->render('stock/show.html.twig', [
            'stock' => $stock,
        ]);
    }

    #[Route('/{id}/edit', name: 'stock_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Stock $stock, EntityManagerInterface $entityManager): Response
    {
        $oldQuantity = $stock->getQuantity();
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newQuantity = $stock->getQuantity();
            $quantityDifference = $newQuantity - $oldQuantity;

            // Update the product stock
            $product = $stock->getProduct();
            if ($product) {
                $currentStock = $product->getStock() ?? 0;
                $product->setStock($currentStock + $quantityDifference);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Stock updated successfully!');

            return $this->redirectToRoute('stock_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/edit.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'stock_delete', methods: ['POST'])]
    public function delete(Request $request, Stock $stock, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $stock->getId(), $request->request->get('_token'))) {
            // Revert the stock quantity from the product
            $product = $stock->getProduct();
            if ($product) {
                $currentStock = $product->getStock() ?? 0;
                $product->setStock($currentStock - $stock->getQuantity());
            }

            $entityManager->remove($stock);
            $entityManager->flush();

            $this->addFlash('success', 'Stock deleted successfully!');
        }

        return $this->redirectToRoute('stock_index', [], Response::HTTP_SEE_OTHER);
    }
}
