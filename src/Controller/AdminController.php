<?php

namespace App\Controller;

use DateTime;
use App\Entity\User;
use App\Entity\Article;
use App\Entity\Category;
use App\Form\AddUserFormType;
use App\Form\ArticleFormType;
use App\Form\EditProfileFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
* @Route("/admin")
*/
class AdminController extends AbstractController
{   
    
     /**
     * @Route("/espace-admin", name="dashboard", methods={"GET"})
     */
     public function showDashboard(EntityManagerInterface $entitymanager): Response
     {  
        // bloquer l'entrée des users excepté l'admin
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        } catch (AccessDeniedException $exception) {
            $this->addFlash('message', 'Cette partie du site est réservée aux admins');
            return $this->redirectToRoute('app_home');
        }  
        est findBy(['deletedAt' => null])
      return $this->render('admin/espaceAdmin.html.twig');
      
    }#END FUNCTION Dashboard
    
     /**
     * @Route("/espace-admin/liste-des-membres", name="users_list", methods={"GET"})
     */
     public function showUser(EntityManagerInterface $entitymanager): Response
     {  
        // bloquer l'entrée des users excepté l'admin
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        } catch (AccessDeniedException $exception) {
            $this->addFlash('message', 'Cette partie du site est réservée aux admins');
            return $this->redirectToRoute('app_home');
        }  
    
      $users = $entitymanager->getRepository(User::class)->findBy(['deletedAt' => null]);
    
      return $this->render('user/users_list.html.twig', [
       'users' => $users,
      ]);
      
    }#END FUNCTION Show User

  
    /**
    * @Route("/espace-admin/ajouter-un-membre", name="create_user", methods={"GET|POST"})
    */
    public function createUser(Request $request, EntityManagerInterface $entitymanager, UserPasswordHasherInterface $userPasswordHasher): Response
    {
       
        $user = new User();
        $form = $this->createForm(AddUserFormType::class, $user);
        $form->handleRequest($request); // traite la requete en mappang  les infos de la rquête à l'objet metier.

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles(['ROLE_USER']);
            $user->setCreatedAt(new DateTime());
            $user->setUpdatedAt(new DateTime());
            
            // encode the plain password
            $user->setPassword(
            $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );
 
                $entitymanager->persist($user);
                $entitymanager->flush();
                           // success et le second ; le message.
                $this->addFlash('message', "Membre ajouté avec succès !");
                return $this->redirectToRoute('users_list');   
            }#END IF

        return $this->render('admin/create_user.html.twig', [
            'addUserForm' => $form->createView()
        ]);
     }#END FUNCTION createUser
     
    /**
    * @Route("/espace-admin/modifier-un-membre-{id}", name="update_user", methods={"GET|POST"})
    */
    public function editProfile(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        // $user = $this->getUser();  pour recup les infos de user
        $form = $this->createForm(EditProfileFormType::class, $user);
        $form->handlerequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
 
            $user->setUpdatedAt(new DateTime()); 

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('message', "membre modifié avec succès !");
            return $this->redirectToRoute('users_list');
                
        } # end if ($form)

        return $this->render('admin/editUser.html.twig', [
            'user' => $user,
            'form' => $form->createView()
        ]);
    }



     /**
     * @Route("/espace-admin/archiver-un-membre-{id}", name="soft_delete_user", methods={"GET|POST"})
     */
     public function softDeleteUser(User $user, EntityManagerInterface $entityManager): Response
     {
       // la variable sur la route {name} ou {id} correspond au nom ou à l'identifiant du user
           $user->setDeletedAt(new DateTime());

           $entityManager->persist($user);
           $entityManager->flush();

           $this->addFlash('message', "Le membre a été archivé ");
           return $this->redirectToRoute('users_list');
       } # end fucntion softDeleteMembre
       
     /**
     * @Route("/espace-admin/restaurer-un-membre_{id}", name="restore_user", methods={"GET"})
     */
     public function restoreUser(User $user, EntityManagerInterface $entityManager): RedirectResponse
     {
        $user->setDeletedAt(null);

        $entityManager->persist($user);
        $entityManager->flush();

        $this->addFlash('message', "Le membre a bien été restauré");
        return $this->redirectToRoute('users_list');
    }

     /**
     * @Route("/espace-admin/supprimer-un-membre_{id}", name="hard_delete_user", methods={"GET"})
     */
     public function hardDeleteUser(User $user, EntityManagerInterface $entityManager): RedirectResponse
     {   
  
         // Suppression manuelle de la photo
         $photo = $user->getPhoto();

       
         if($photo) {
             unlink($this->getParameter('uploads_dir'). '/' . $photo); 
         }
       
        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('message', "Le membre a bien été supprimé de la base de données");
        return $this->redirectToRoute('user_trash');
     }

       
     /**
     * @Route("/espace-admin/voir-les-membres-archives", name="user_trash", methods={"GET"})
     */
     public function showTrashUser(EntityManagerInterface $entityManager): Response
     { 
       $archivedusers = $entityManager->getRepository(User::class)->findByTrash();
       
       return $this->render("admin/trash/user_trash.html.twig", [
           'archivedusers' =>  $archivedusers
       ]);
     }

     /**
     * @Route("/espace-admin/ajouter-un-article", name="create_article", methods={"GET|POST"})
     */
     public function createArticle(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
     {
        # 1 - Instanciation
        $article = new Article();

        # 2 - Création du formulaire
        $form = $this->createForm(ArticleFormType::class, $article)
           ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $article->setCreatedAt(new DateTime());
            $article->setUpdatedAt(new DateTime());

            $article->setAlias($slugger->slug($article->getTitle()));

            /** @var UploadedFile $photo */
            $photo = $form->get('photo')->getData();

          .
            if($photo) {
                # Déconstructioon
                $extension = '.' . $photo->guessExtension();
                $originalFilename = pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $safeFilename = $article->getAlias();


                $newFilename = $safeFilename . '_' . uniqid() . $extension;

                try {
                    $photo->move($this->getParameter('uploads_dir'), $newFilename);
                    $article->setPhoto($newFilename);
                }
                catch(FileException $exception) {
                    # Code à exécuter en cas d'erreur.
                }
            } //else { 

           // }# end if($photo)

                # Ajout d'un auteur à l'article (User récupéré depuis la session)
                $article->setAuthor($this->getUser());

                $entityManager->persist($article);
                $entityManager->flush(); 
                $this->addFlash('message', "L'article est en ligne avec succès !");
                return $this->redirectToRoute('articles_list');

        } # end if ($form)

        # 3 - Création de la vue
        return $this->render("admin/form/article.html.twig", [
            'form' => $form->createView()
        ]);
    } # end function createArticle

    
    /**
    * @Route("/espace-admin/liste-des-articles", name="articles_list", methods={"GET"})
    */
    public function showArticle(EntityManagerInterface $entityManager): Response
    {  
       // bloquer l'entrée des users excepté l'admin
       try {
           $this->denyAccessUnlessGranted('ROLE_ADMIN');
       } catch (AccessDeniedException $exception) {
           $this->addFlash('message', 'Cette partie du site est réservée aux admins');
           return $this->redirectToRoute('app_home');
       }  

       $articles = $entityManager->getRepository(Article::class)->findBy(['deletedAt' => null]);
          return $this->render('article/articles_list.html.twig', [
       'articles' => $articles,
     ]);
     
   }#END FUNCTION Show Articles
    /**
    * @Route("/espace-admin/modifier-un-article_{id}", name="update_article", methods={"GET|POST"})
    */
    public function updateArticle(Article $article, Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {  
    
        $originalPhoto = $article->getPhoto();

        # 2 - Création du formulaire
        $form = $this->createForm(ArticleFormType::class, $article, [
           'photo' => $originalPhoto
        ]);
        $form ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $article->setUpdatedAt(new DateTime());

            $article->setAlias($slugger->slug($article->getTitle()));

            /** @var UploadedFile $photo */
            $photo = $form->get('photo')->getData();

            if($photo) {

                # Déconstructioon
                $extension = '.' . $photo->guessExtension();
                $originalFilename = pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
          $safeFilename = $article->getAlias();

                # Reconstruction
                $newFilename = $safeFilename . '_' . uniqid() . $extension;

                try {
                    $photo->move($this->getParameter('uploads_dir'), $newFilename);
                    $article->setPhoto($newFilename);
                }
                catch(FileException $exception) {
                    # Code à exécuter en cas d'erreur.
                }

            } # end if($photo)

            # Ajout d'un auteur à l'article (User récupéré depuis la session)
            $article->setAuthor($this->getUser());

            $entityManager->persist($article);
            $entityManager->flush();

            $this->addFlash('message', "L'article a été modifié avec succès !");
            return $this->redirectToRoute('articles_list');
        } # end if ($form)

        # 3 - Création de la vue
        return $this->render("admin/form/article.html.twig", [
            'form' => $form->createView(),
            'article' => $article
        ]);
    }# end function updateArticle

    /**
     * @Route("/espace-admin/archiver-un-article_{id}", name="soft_delete_article", methods={"GET"})
     */
    public function softDeleteArticle(Article $article, EntityManagerInterface $entityManager): Response
    {
        $article->setDeletedAt(new DateTime());

        $entityManager->persist($article);
        $entityManager->flush();

        $this->addFlash('message', "L'article a bien été archivé");
        return $this->redirectToRoute('articles_list');
    }# end function softDelete

    
    /**
     * @Route("/espace-admin/restaurer-un-article_{id}", name="restore_article", methods={"GET"})
     */
    public function restoreArticle(Article $article, EntityManagerInterface $entityManager): RedirectResponse
    {
        $article->setDeletedAt(null);

        $entityManager->persist($article);
        $entityManager->flush();

        $this->addFlash('message', "L'article a bien été restauré");
        return $this->redirectToRoute('articles_list');
    }

  
     /**
     * @Route("/espace-admin/supprimer-un-article_{id}", name="hard_delete_article", methods={"GET"})
     */
     public function hardDeleteArticle(Article $article, EntityManagerInterface $entityManager): RedirectResponse
     {
        // Suppression manuelle de la photo
        $photo = $article->getPhoto();


        if($photo) {
            unlink($this->getParameter('uploads_dir'). '/' . $photo); 
        }

        $entityManager->remove($article);
        $entityManager->flush();

        $this->addFlash('message', "L'article a bien été supprimé de la base de données");
        return $this->redirectToRoute('article_trash');
    }
   
    /**
    * @Route("/espace-admin/voir-les-articles-archives", name="article_trash", methods={"GET"})
    */
    public function showTrashArticles(EntityManagerInterface $entityManager): Response
    {   
        $archivedArticles = $entityManager->getRepository(Article::class)->findByTrash();

        return $this->render("admin/trash/article_trash.html.twig", [
            'archivedArticles' => $archivedArticles
        ]);
    }
   
     /**
     * @Route("/espace-admin/liste-des-categories", name="category_list", methods={"GET"})
     */
     public function showCategory(EntityManagerInterface $entityManager): Response
     {  

       try {
           $this->denyAccessUnlessGranted('ROLE_ADMIN');
       } catch (AccessDeniedException $exception) {
           $this->addFlash('message', 'Cette partie du site est réservée aux admins');
           return $this->redirectToRoute('app_home');
       }  
   
       $categories = $entityManager->getRepository(Category::class)->findBy(['deletedAt' => null]);
              return $this->render('category/category_list.html.twig', [
               'categories' => $categories,
     ]);
     
     }#END FUNCTION Show Category


}
