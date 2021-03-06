<?php

namespace App\Controller;

use App\Entity\Personne;
use App\Form\PersonneType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/personne')]
class PersonneController extends AbstractController
{
    #[Route('', name: 'personne.list')]
    public function index(ManagerRegistry $doctrine):Response{
        $repository=$doctrine->getRepository(Personne::class);
        $personnes=$repository->findAll();
        return $this->render("personne/index.html.twig",['personnes'=>$personnes]);
    }


    #[Route('/page/{page?1}/{nbr?12}', name: 'personne.list.page')]
    public function all(ManagerRegistry $doctrine,$page,$nbr):Response{
        $repository=$doctrine->getRepository(Personne::class);
        $nbPersonnes=$repository->count([]);
        $nbPages=ceil($nbPersonnes / $nbr);
        $personnes=$repository->findBy([],[],$nbr,$nbr*($page -1));
        return $this->render('personne/page.html.twig',
            ['nbPages'=>$nbPages,'page'=>$page,'isPaginated'=>True,'personnes'=>$personnes,'nbr'=>$nbr]);
    }

   /* #[Route('/all', name: 'personne.list.all')]
    public function all(ManagerRegistry $doctrine):Response{
        $repository=$doctrine->getRepository(Personne::class);
        $personnes=$repository->findBy(['prénom'=>'Dora']);
        #$personnes=$repository->findBy([],['age'=>'ASC'],5,2);
        return $this->render("personne/index.html.twig",['personnes'=>$personnes]);
    }*/

    #[Route('/{id<\d+>}', name: 'personne.detail')]
    public function detail(ManagerRegistry $doctrine,$id):Response{
        $repository=$doctrine->getRepository(Personne::class); // I can delete this and the line under it
        $personne=$repository->find($id); //and change the parameter with (Personne $personne=null)
        if(!$personne){  // et cette méthode s'appelle param converter
            $this->addFlash('error',"La personne d'id $id n'existe pas ."); // and delete this $id
            return $this->redirectToRoute('personne.list');
        }
            return $this->render("personne/detail.html.twig", ['personne' => $personne]);

    }



//    #[Route('/add', name: 'personne.add')]
    /*public function addPersonne(ManagerRegistry $doctrine): Response
    {
        $entityManager=$doctrine->getManager();
        $personne=new Personne();
        $personne->setNom('Kouki');
        $personne->setPrénom('Chedi');
        $personne->setAge(24);
        // add the operation to the transaction
        $entityManager->persist($personne);
        // execute the transaction
        $entityManager->flush();
        return $this->render('personne/detail.html.twig',['personne'=>$personne]);
    }*/

    #[Route('/add', name: 'personne.add')]
    public function addPersonne(ManagerRegistry $doctrine,Request $request): Response
    {

        $personne= new Personne();
        $form=$this->createForm(PersonneType::class,$personne);
        $form->remove('createdAt');
        $form->remove('updatedAt');
        $form->handleRequest($request);
        if($form->isSubmitted()){
            $entityManager=$doctrine->getManager();
            $entityManager->persist($personne);
            $entityManager->flush();
            $this->addFlash('success','Inscription réussie');
            return $this->redirectToRoute('personne.list');
        }
        else{
            return $this->render('personne/add-form.html.twig',['form'=>$form->createView()]);
        }

    }

    #[Route('/edit/{id?0}', name: 'personne.edit')]
    public function editPersonne(Personne $personne=null,ManagerRegistry $doctrine,Request $request,SluggerInterface $slugger): Response
    {
        $new=False;
        if(!$personne){
            $personne= new Personne();
            $new=True;
        }

        $form=$this->createForm(PersonneType::class,$personne);
        $form->remove('createdAt');
        $form->remove('updatedAt');
        $form->handleRequest($request);
        if($form->isSubmitted() ){
            $entityManager=$doctrine->getManager();

            $img = $form->get('photo')->getData();
            if ($img) {
                $originalFilename = pathinfo($img->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $img->guessExtension();

                // Move the file to the directory where images are stored
                try {
                    $img->move(
                        $this->getParameter('personnes_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                // updates the 'image' property to store the Image file name
                // instead of its contents
                $personne->setImage($newFilename);

            }
            $entityManager->persist($personne);
            $entityManager->flush();
            if($new==True){
                $message=" a été ajouté avec succés .";
            }else{
                $message=" a été edité avec succés .";
            }
            $this->addFlash('success',$personne->getNom().$message);
            return $this->redirectToRoute('personne.list');
        }
        else{
            return $this->render('personne/add-form.html.twig',['form'=>$form->createView()]);
        }

    }



    #[Route('/delete/{id<\d+>}', name: 'personne.delete')]
    public function deletePersonne(ManagerRegistry $doctrine,$id): RedirectResponse
    {

        $repository = $doctrine->getRepository(Personne::class);
        $personne = $repository->find($id);
        if(!$personne){
            $this->addFlash('error',"La personne d'id $id n'existe pas");
        }else {
            $entityManager = $doctrine->getManager();
            $entityManager->remove($personne);
            $entityManager->flush();
            $this->addFlash('success', "La personne d'id $id a été supprimée avec succés");
        }
        return $this->redirectToRoute('personne.list');
    }

    #[Route('/update/{id}/{nom}/{prenom}/{age}', name: 'personne.update')]
    public function updatePersonne(ManagerRegistry $doctrine,$id,$nom,$prenom,$age): RedirectResponse
    {
        $repository=$doctrine->getRepository(Personne::class);
        $personne=$repository->find($id);
        if(!$personne){
            $this->addFlash('error',"La personne d'id $id n'existe pas");
        }else{
            $personne->setNom($nom);
            $personne->setPrénom($prenom);
            $personne->setAge($age);
            $entityManager=$doctrine->getManager();
            $entityManager->persist($personne);
            $entityManager->flush();
            $this->addFlash('success', "La personne d'id $id a été mise à jour avec succés");
        }
        return $this->redirectToRoute('personne.list');
    }


    #[Route('/age/{min}/{max}', name: 'personne.ageInterval')]
    public function ageInterval(ManagerRegistry $doctrine,$min,$max):Response
    {
        $repository=$doctrine->getRepository(Personne::class);
        $personnes=$repository->findPersonneByAgeInterval($min,$max);
        $stats=$repository->findStatsPersonneByAgeInterval($min,$max);
        return $this->render("personne/index.html.twig",['personnes'=>$personnes,'stats'=>$stats,'ageMin'=>$min,'ageMax'=>$max]);


    }

}
