<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ComputedField;

use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\ComputedExpressionArtist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\ComputedExpressionLabel;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\ComputedExpressionRecording;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;

/**
 * Two labels, five artists, and the artists' recordings
 *
 * The artists, by identifier: Phish (Elektra, 4 recordings), Ween (Elektra,
 * 2), moe. (Elektra, 0), Gov't Mule (Relix, 3) and Grateful Dead (Relix, 1).
 */
trait ComputedExpressionData
{
    private function populateComputedExpressionData(EntityManager $entityManager): void
    {
        $labels = [];
        foreach (['Elektra', 'Relix'] as $name) {
            $labels[$name] = new ComputedExpressionLabel($name);
            $entityManager->persist($labels[$name]);
        }

        // Each artist's recordings, by the date of their release
        $artists = [
            ['Phish', 'Elektra', ['2002-05-14', '2002-11-05', '2003-10-14', '2004-06-15']],
            ['Ween', 'Elektra', ['2003-07-15', '2007-10-23']],
            ['moe.', 'Elektra', []],
            ["Gov't Mule", 'Relix', ['2001-06-12', '2003-09-23', '2004-08-17']],
            ['Grateful Dead', 'Relix', ['1977-05-08']],
        ];

        foreach ($artists as [$name, $label, $releases]) {
            $artist = (new ComputedExpressionArtist($name))->setLabel($labels[$label]);
            $entityManager->persist($artist);

            foreach ($releases as $i => $released) {
                $entityManager->persist(new ComputedExpressionRecording(
                    $name . ' ' . $i,
                    new DateTimeImmutable($released),
                    $artist,
                ));
            }
        }

        $entityManager->flush();
        $entityManager->clear();
    }
}
