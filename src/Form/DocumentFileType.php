<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use App\Entity\DocumentFile;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\File;

class DocumentFileType extends AbstractType {

    public function buildForm(FormBuilderInterface $builder, array $options) {
        
        $edit = isset($options['data']) && $options['data']->getId();
        
        if (!$edit) {
            $builder
                ->add('file', FileType::class, [
                        'label' => 'Wybierz plik',
                        'mapped' => false, // Pole nie jest mapowane bezpośrednio na encję
                        'required' => true,
                        'attr' => ['class' => 'form-control'],
                        'constraints' => [
                            new File([
                                'maxSize' => '10m',
                                'mimeTypes' => [
                                    'application/pdf', // PDF
                                    'application/msword', // DOC
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // DOCX
                                    'application/vnd.ms-excel', // XLS
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // XLSX
                                    'application/vnd.ms-powerpoint', // PPT
                                    'application/vnd.openxmlformats-officedocument.presentationml.presentation', // PPTX
                                    'application/vnd.oasis.opendocument.text', // ODT (LibreOffice/OpenOffice Text)
                                    'application/vnd.oasis.opendocument.spreadsheet', // ODS (LibreOffice/OpenOffice Spreadsheet)
                                    'application/vnd.oasis.opendocument.presentation', // ODP (LibreOffice/OpenOffice Presentation)
                                    'image/*', // Obrazy
                                    'application/x-freemind',
                                    'application/x-xmind',
                                ],
                                'mimeTypesMessage' => 'Proszę przesłać poprawny plik (PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ODT, ODS, ODP, MMAP, XMIND, MM, obrazy).',
                            ]),
                        ],
                    ])
                ;
        }
        
        $builder
                ->add('name', TextareaType::class, [
                    'label' => 'Tytuł pliku',
                    'attr' => ['class' => 'form-control-file'],
                ])
                ->add('description', TextareaType::class, [
                    'label' => 'Opis pliku',
                    'attr' => ['class' => 'form-control', 'rows' => 5], // Dodaj Bootstrapową klasę
                    'required' => false,
                ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'data_class' => DocumentFile::class,
        ]);
    }
}
